<?php


namespace TheCodingMachine\TDBM\Bundle\DependencyInjection;


use BrainDiminished\SchemaVersionControl\SchemaVersionControlService;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use TheCodingMachine\TDBM\Bundle\Utils\StubSchemaManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use TheCodingMachine\TDBM\Bundle\Utils\DoctrineCacheClearer;
use TheCodingMachine\TDBM\Bundle\Utils\SymfonyCodeGeneratorListener;
use TheCodingMachine\TDBM\Commands\GenerateCommand;
use TheCodingMachine\TDBM\Configuration as TDBMConfiguration;
use TheCodingMachine\TDBM\ConfigurationInterface;
use TheCodingMachine\TDBM\Schema\LockFileSchemaManager;
use TheCodingMachine\TDBM\TDBMService;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\DefaultNamingStrategy;
use TheCodingMachine\TDBM\Utils\NamingStrategyInterface;
use TheCodingMachine\TDBM\SchemaLockFileDumper;
use TheCodingMachine\TDBM\Utils\RootProjectLocator;
use function class_exists;
use function file_exists;
use function ltrim;
use function strlen;
use function strpos;
use function substr;
use function var_dump;

class TdbmExtension extends Extension
{
    private const DEFAULT_CONFIGURATION_ID = TDBMConfiguration::class;
    private const DEFAULT_NAMING_STRATEGY_ID = DefaultNamingStrategy::class;

    /**
     * Loads a specific configuration.
     *
     * @param mixed[] $configs
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $processedConfig = $this->processConfiguration($configuration, $configs);

        $config = new TdbmExtensionConfiguration($processedConfig);

        $container->setDefinition('tdbm.cache', $this->getCacheDefinition());
        $container->setDefinition('tdbm.cacheclearer', $this->getCacheClearerDefinition());
        $container->setAlias(ConfigurationInterface::class, self::DEFAULT_CONFIGURATION_ID);
        $container->setAlias(NamingStrategyInterface::class, self::DEFAULT_NAMING_STRATEGY_ID);
        $container->setDefinition(AnnotationParser::class, $this->getAnnotationParserDefinition());
        $container->setDefinition(SymfonyCodeGeneratorListener::class, $this->nD(SymfonyCodeGeneratorListener::class));

        $container->addDefinitions($this->getDatabaseDefinitions('', $config->getDefaultConfiguration()));

        foreach ($config->getDatabases() as $databaseIdentifier => $databaseConfiguration) {
            $container->addDefinitions($this->getDatabaseDefinitions($databaseIdentifier, $databaseConfiguration));
        }
    }

    /**
     * Utility function to create a `new Definition()` with default values
     *
     * @param mixed[] $arguments
     */
    private function nD(string $class = null, array $arguments = []): Definition
    {
        $definition = new Definition($class, $arguments);
        $definition->setAutowired(true);
        $definition->setAutoconfigured(true);
        $definition->setPublic(false);

        return $definition;
    }

    /**
     * @return array<string, Definition>
     */
    private function getDatabaseDefinitions(string $identifier, ConnectionConfiguration $config): array
    {
        $identifierSuffix = $identifier === '' ? '' : '.' . $identifier;
        $commandName = $identifier === '' ? 'tdbm:generate' : 'tdbm:generate:' . $identifier;

        $configurationServiceId = self::DEFAULT_CONFIGURATION_ID . $identifierSuffix;
        $schemaManagerServiceId = 'tdbm.SchemaManager' . $identifierSuffix;
        $connectionServiceId = $config->getConnection();
        $namingStrategyServiceId = self::DEFAULT_NAMING_STRATEGY_ID . $identifierSuffix;
        $schemaLockFileDumperServiceId = SchemaLockFileDumper::class . $identifierSuffix;
        $lockFileSchemaManagerServiceId = LockFileSchemaManager::class . $identifierSuffix;

        // Now let's create the DAOs.
        $tdbmLockFilePath = $this->getLockFilePath($config->getConnection());

        $daos = [];
        if (file_exists($tdbmLockFilePath)) {
            $schemaVersionControlService = new SchemaVersionControlService(new Connection([], new Driver()), $tdbmLockFilePath);
            $schema = $schemaVersionControlService->loadSchemaFile();
            $namingStrategy = $this->getNamingStrategy($config, $schema);

            foreach ($schema->getTables() as $table) {
                $className = ltrim($config->getDaoNamespace(), '\\').'\\'.$namingStrategy->getDaoClassName($table->getName());
                if (!class_exists($className)) {
                    continue;
                }
                $dao = $this->nD($className);
                $dao->setArgument('$tdbmService', new Reference(TDBMService::class . $identifierSuffix));
                $daos[$className] = $dao;
            }
        }

        return array_merge([
            $configurationServiceId => $this->getConfigurationDefinition($config, $namingStrategyServiceId),
            $namingStrategyServiceId => $this->getNamingStrategyDefinition($config, $lockFileSchemaManagerServiceId),
            TDBMService::class . $identifierSuffix => $this->getTDBMServiceDefinition($configurationServiceId),
            GenerateCommand::class . $identifierSuffix => $this->getGenerateCommandDefinition($commandName, $configurationServiceId),
            $schemaManagerServiceId => $this->getSchemaManagerDefinition($connectionServiceId),
            $lockFileSchemaManagerServiceId => $this->getLockFileSchemaManagerDefinition($schemaManagerServiceId, $schemaLockFileDumperServiceId),
            $schemaLockFileDumperServiceId => $this->getSchemaLockFileDumperDefinition($connectionServiceId, 'tdbm' . $identifierSuffix . '.lock.yml'),
        ], $daos);
    }

    private function getConfigurationDefinition(ConnectionConfiguration $config, string $namingStrategyServiceId): Definition
    {
        $configuration = $this->nD(TDBMConfiguration::class);
        $configuration->setArgument(0, $config->getBeanNamespace());
        $configuration->setArgument(1, $config->getDaoNamespace());
        $configuration->setArgument('$connection', new Reference($config->getConnection()));
        $configuration->setArgument('$namingStrategy', new Reference($namingStrategyServiceId));
        $configuration->setArgument('$codeGeneratorListeners', [new Reference(SymfonyCodeGeneratorListener::class)]);
        $configuration->setArgument('$cache', new Reference('tdbm.cache'));

        // Let's name the tdbm lock file after the name of the DBAL connection.

        $connectionName = $config->getConnection();

        if ($connectionName !== 'doctrine.dbal.default_connection') {
            $configuration->setArgument('$lockFilePath', $this->getLockFilePath($connectionName));
        }

        return $configuration;
    }

    private function getLockFilePath(string $connectionName): string {
        // A DBAL connection is in the form: "doctrine.dbal.default_connection"
        if (strpos($connectionName, 'doctrine.dbal.') === 0) {
            $connectionName = substr($connectionName, 14);
            if (strpos($connectionName, '_connection') === strlen($connectionName) - 11) {
                $connectionName = substr($connectionName, 0, strlen($connectionName) - 11);
            }
        }

        if ($connectionName === 'default') {
            return RootProjectLocator::getRootLocationPath().'tdbm.lock.yml';
        }

        return RootProjectLocator::getRootLocationPath().'tdbm.'.$connectionName.'.lock.yml';
    }

    private function getCacheDefinition(): Definition
    {
        $cache = $this->nD(FilesystemCache::class);
        $cache->setArgument(0, '%kernel.project_dir%/var/cache/tdbm');
        return $cache;
    }

    private function getCacheClearerDefinition(): Definition
    {
        $cache = $this->nD(DoctrineCacheClearer::class);
        $cache->setArgument(0, new Reference('tdbm.cache'));
        $cache->addTag('kernel.cache_clearer');
        return $cache;
    }

    private function getNamingStrategyDefinition(ConnectionConfiguration $config, string $schemaManagerServiceId): Definition
    {
        $namingStrategy = $this->nD(DefaultNamingStrategy::class);
        $namingStrategy->setArgument('$schemaManager', new Reference($schemaManagerServiceId));
        $namingStrategy->addMethodCall('setBeanPrefix', [$config->getNamingBeanPrefix()]);
        $namingStrategy->addMethodCall('setBeanSuffix', [$config->getNamingBeanSuffix()]);
        $namingStrategy->addMethodCall('setBaseBeanPrefix', [$config->getNamingBaseBeanPrefix()]);
        $namingStrategy->addMethodCall('setBaseBeanSuffix', [$config->getNamingBaseBeanSuffix()]);
        $namingStrategy->addMethodCall('setDaoPrefix', [$config->getNamingDaoPrefix()]);
        $namingStrategy->addMethodCall('setDaoSuffix', [$config->getNamingDaoSuffix()]);
        $namingStrategy->addMethodCall('setBaseDaoPrefix', [$config->getNamingBaseDaoPrefix()]);
        $namingStrategy->addMethodCall('setBaseDaoSuffix', [$config->getNamingBaseDaoSuffix()]);
        $namingStrategy->addMethodCall('setExceptions', [$config->getNamingExceptions()]);

        return $namingStrategy;
    }

    private function getNamingStrategy(ConnectionConfiguration $config, Schema $schema): NamingStrategyInterface
    {
        $namingStrategy = new DefaultNamingStrategy(AnnotationParser::buildWithDefaultAnnotations([]), new StubSchemaManager($schema));
        $namingStrategy->setBeanPrefix($config->getNamingBeanPrefix());
        $namingStrategy->setBeanSuffix($config->getNamingBeanSuffix());
        $namingStrategy->setBaseBeanPrefix($config->getNamingBaseBeanPrefix());
        $namingStrategy->setBaseBeanSuffix($config->getNamingBaseBeanSuffix());
        $namingStrategy->setDaoPrefix($config->getNamingDaoPrefix());
        $namingStrategy->setDaoSuffix($config->getNamingDaoSuffix());
        $namingStrategy->setBaseDaoPrefix($config->getNamingBaseDaoPrefix());
        $namingStrategy->setBaseDaoSuffix($config->getNamingBaseDaoSuffix());
        $namingStrategy->setExceptions($config->getNamingExceptions());

        return $namingStrategy;
    }

    private function getTDBMServiceDefinition(string $configurationServiceId): Definition
    {
        $tdbmService = $this->nD(TDBMService::class);
        $tdbmService->setArgument(0, new Reference($configurationServiceId));
        $tdbmService->setPublic(true);
        return $tdbmService;
    }

    private function getGenerateCommandDefinition(string $commandName, string $configurationServiceId): Definition
    {
        $generateCommand = $this->nD(GenerateCommand::class);
        $generateCommand->setArgument(0, new Reference($configurationServiceId));
        $generateCommand->addTag('console.command');
        $generateCommand->setPublic(true);
        $generateCommand->addMethodCall('setName', [$commandName]);
        return $generateCommand;
    }

    private function getAnnotationParserDefinition(): Definition
    {
        $annotationParser = $this->nD(AnnotationParser::class);
        $annotationParser->setFactory([AnnotationParser::class, 'buildWithDefaultAnnotations']);
        $annotationParser->setArgument(0, []);

        return $annotationParser;
    }

    private function getSchemaManagerDefinition(string $connectionServiceId): Definition
    {
        $schemaManager = $this->nD(AbstractSchemaManager::class);
        $schemaManager->setFactory([new Reference($connectionServiceId), 'getSchemaManager']);

        return $schemaManager;
    }

    private function getLockFileSchemaManagerDefinition(string $schemaManagerServiceId, string $schemaLockFileDumperServiceId): Definition
    {
        $lockFileSchemaManager = $this->nD(LockFileSchemaManager::class);
        $lockFileSchemaManager->setDecoratedService($schemaManagerServiceId);
        $lockFileSchemaManager->setArgument(0, new Reference('TheCodingMachine\TDBM\Schema\LockFileSchemaManager.inner'));
        $lockFileSchemaManager->setArgument(1, new Reference($schemaLockFileDumperServiceId));

        return $lockFileSchemaManager;
    }

    private function getSchemaLockFileDumperDefinition(string $connectionServiceId, string $lockFileName): Definition
    {
        $lockFileSchemaManager = $this->nD(SchemaLockFileDumper::class);
        $lockFileSchemaManager->setArgument(0, new Reference($connectionServiceId));
        $lockFileSchemaManager->setArgument(1, new Reference('tdbm.cache'));
        $lockFileSchemaManager->setArgument(2, '%kernel.project_dir%/' . $lockFileName);

        return $lockFileSchemaManager;
    }
}
