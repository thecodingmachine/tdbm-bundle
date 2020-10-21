<?php


namespace TheCodingMachine\TDBM\Bundle\DependencyInjection;


use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
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
        $container->setDefinition(GenerateCommand::class, $this->getGenerateCommandDefinition());
        $container->setDefinition(AnnotationParser::class, $this->getAnnotationParserDefinition());
        $container->setDefinition(SymfonyCodeGeneratorListener::class, $this->nD(SymfonyCodeGeneratorListener::class));

        $container->addDefinitions($this->getDatabaseDefinitions('', $config->getDefaultConfiguration()));

        foreach ($config->getDatabases() as $databaseIdentifier => $databaseConfiguration) {
            $container->addDefinitions($this->getDatabaseDefinitions('.' . $databaseIdentifier, $databaseConfiguration));
        }
    }

    /**
     * Utility function to create a `new Definition()` with default values
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
    private function getDatabaseDefinitions(string $identifiersSuffix, ConnectionConfiguration $config): array
    {
        $schemaManagerServiceId = 'tdbm.SchemaManager' . $identifiersSuffix;
        $connectionServiceId = $config->getConnection();

        return [
            self::DEFAULT_CONFIGURATION_ID . $identifiersSuffix => $this->getConfigurationDefinition($config),
            self::DEFAULT_NAMING_STRATEGY_ID . $identifiersSuffix => $this->getNamingStrategyDefinition($config, $schemaManagerServiceId),
            TDBMService::class . $identifiersSuffix => $this->getTDBMServiceDefinition(),
            $schemaManagerServiceId => $this->getSchemaManagerDefinition($connectionServiceId),
            LockFileSchemaManager::class . $identifiersSuffix => $this->getLockFileSchemaManagerDefinition(),
            SchemaLockFileDumper::class . $identifiersSuffix => $this->getSchemaLockFileDumperDefinition('tdbm' . $identifiersSuffix . '.lock.yml'),
        ];
    }

    private function getConfigurationDefinition(ConnectionConfiguration $config): Definition
    {
        $configuration = $this->nD(TDBMConfiguration::class);
        $configuration->setArgument(0, $config->getBeanNamespace());
        $configuration->setArgument(1, $config->getDaoNamespace());
        $configuration->setArgument('$connection', new Reference($config->getConnection()));
        $configuration->setArgument('$codeGeneratorListeners', [new Reference(SymfonyCodeGeneratorListener::class)]);
        $configuration->setArgument('$cache', new Reference('tdbm.cache'));
        return $configuration;
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

    private function getTDBMServiceDefinition(): Definition
    {
        $tdbmService = $this->nD(TDBMService::class);
        $tdbmService->setPublic(true);
        return $tdbmService;
    }

    private function getGenerateCommandDefinition(): Definition
    {

        $generateCommand = $this->nD(GenerateCommand::class);
        $generateCommand->addTag('console.command');
        $generateCommand->setPublic(true);
        return $generateCommand;
    }

    private function getAnnotationParserDefinition(): Definition
    {
        $annotationParser = $this->nD(AnnotationParser::class);
        $annotationParser->setFactory([AnnotationParser::class, 'buildWithDefaultAnnotations']);
        $annotationParser->setArgument(0, []);

        return $annotationParser;
    }

    private function getSchemaManagerDefinition(string $connectionService): Definition
    {
        $schemaManager = $this->nD(AbstractSchemaManager::class);
        $schemaManager->setFactory([new Reference($connectionService), 'getSchemaManager']);

        return $schemaManager;
    }

    private function getLockFileSchemaManagerDefinition(): Definition
    {
        $lockFileSchemaManager = $this->nD(LockFileSchemaManager::class);
        $lockFileSchemaManager->setArgument(0, new Reference('TheCodingMachine\TDBM\Schema\LockFileSchemaManager.inner'));
        $lockFileSchemaManager->setArgument(1, new Reference(SchemaLockFileDumper::class));

        return $lockFileSchemaManager;
    }

    private function getSchemaLockFileDumperDefinition(string $lockFileName): Definition
    {
        $lockFileSchemaManager = $this->nD(SchemaLockFileDumper::class);
        $lockFileSchemaManager->setArgument(0, new Reference('doctrine.dbal.default_connection'));
        $lockFileSchemaManager->setArgument(1, new Reference('tdbm.cache'));
        $lockFileSchemaManager->setArgument(2, '%kernel.project_dir%/' . $lockFileName);

        return $lockFileSchemaManager;
    }
}
