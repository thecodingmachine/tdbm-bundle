<?php


namespace TheCodingMachine\TDBM\Bundle\Tests;


use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use TheCodingMachine\TDBM\Bundle\TdbmBundle;

class TdbmTestingKernel extends Kernel
{
    use MicroKernelTrait;

    const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles()
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new DoctrineBundle(),
            new TdbmBundle(),
        ];
    }

    public function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $loader->load(function(ContainerBuilder $container) {
            $container->loadFromExtension('framework', array(
                'secret' => 'S0ME_SECRET',
            ));
            $container->loadFromExtension('doctrine', array(
                'dbal' => [
                    'driver' => 'pdo_mysql',
                    'server_version' => '5.7',
                    'charset'=> 'utf8mb4',
                    'default_table_options' => [
                        'charset' => 'utf8mb4',
                        'collate' => 'utf8mb4_unicode_ci',
                    ],
                    'url' => '%env(resolve:DATABASE_URL)%'
                ]
            ));
        });
        $confDir = $this->getProjectDir().'/Tests/Fixtures/config';

        $loader->load($confDir.'/{packages}/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{packages}/'.$this->environment.'/**/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}_'.$this->environment.self::CONFIG_EXTS, 'glob');
    }

    protected function configureRoutes(RoutingConfigurator $routes)
    {
    }

    public function getCacheDir()
    {
        return __DIR__.'/../cache/'.spl_object_hash($this);
    }
}
