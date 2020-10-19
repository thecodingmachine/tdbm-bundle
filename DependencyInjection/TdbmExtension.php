<?php


namespace TheCodingMachine\TDBM\Bundle\DependencyInjection;


use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
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
        $config = $this->processConfiguration($configuration, $configs);

        $container->setDefinition(TDBMConfiguration::class, $this->getConfigurationDefinition($config));
        $container->setDefinition('tdbm.cache', $this->getCacheDefinition());
        $container->setDefinition('tdbm.cacheclearer', $this->getCacheClearerDefinition());
        $container->setAlias(ConfigurationInterface::class, TDBMConfiguration::class);
        $container->setAlias(NamingStrategyInterface::class, DefaultNamingStrategy::class);
        $container->setDefinition(DefaultNamingStrategy::class, $this->getNamingStrategyDefinition($config['naming'] ?? []));
        $container->setDefinition(TDBMService::class, ($this->nD(TDBMService::class))->setPublic(true));
        $container->setDefinition(
            GenerateCommand::class,
            ($this->nD(GenerateCommand::class))->addTag('console.command')->setPublic(true)
        );
        $container->setDefinition(AnnotationParser::class, $this->getAnnotationParserDefinition());
        $container->setDefinition('tdbm.SchemaManager', $this->getSchemaManagerDefinition());
        $container->setDefinition(LockFileSchemaManager::class, $this->getLockFileSchemaManagerDefinition());
        $container->setDefinition(SchemaLockFileDumper::class, $this->getSchemaLockFileDumperDefinition('tdbm.lock.yml'));
        $container->setDefinition(SymfonyCodeGeneratorListener::class, $this->nD(SymfonyCodeGeneratorListener::class));
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

    private function getConfigurationDefinition(array $config): Definition
    {
        $configuration = $this->nD(TDBMConfiguration::class);
        $configuration->setArgument(0, $config['bean_namespace']);
        $configuration->setArgument(1, $config['dao_namespace']);
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

    private function getNamingStrategyDefinition(array $config): Definition
    {
        $namingStrategy = $this->nD(DefaultNamingStrategy::class);
        $namingStrategy->setArgument('$schemaManager', new Reference('tdbm.SchemaManager'));
        $namingStrategy->addMethodCall('setBeanPrefix', [$config['bean_prefix'] ?? '']);
        $namingStrategy->addMethodCall('setBeanSuffix', [$config['bean_suffix'] ?? '']);
        $namingStrategy->addMethodCall('setBaseBeanPrefix', [$config['base_bean_prefix'] ?? 'Abstract']);
        $namingStrategy->addMethodCall('setBaseBeanSuffix', [$config['base_bean_suffix'] ?? '']);
        $namingStrategy->addMethodCall('setDaoPrefix', [$config['dao_prefix'] ?? '']);
        $namingStrategy->addMethodCall('setDaoSuffix', [$config['dao_suffix'] ?? 'Dao']);
        $namingStrategy->addMethodCall('setBaseDaoPrefix', [$config['base_dao_prefix'] ?? 'Abstract']);
        $namingStrategy->addMethodCall('setBaseDaoSuffix', [$config['base_dao_suffix'] ?? 'Dao']);
        $namingStrategy->addMethodCall('setExceptions', [$config['exceptions'] ?? []]);

        return $namingStrategy;
    }

    private function getAnnotationParserDefinition(): Definition
    {
        $annotationParser = $this->nD(AnnotationParser::class);
        $annotationParser->setFactory([AnnotationParser::class, 'buildWithDefaultAnnotations']);
        $annotationParser->setArgument(0, []);

        return $annotationParser;
    }

    private function getSchemaManagerDefinition(): Definition
    {
        $schemaManager = $this->nD(AbstractSchemaManager::class);
        $schemaManager->setFactory([new Reference('doctrine.dbal.default_connection'), 'getSchemaManager']);

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
