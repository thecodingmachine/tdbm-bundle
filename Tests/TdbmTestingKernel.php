<?php


namespace TheCodingMachine\TDBM\Bundle\Tests;


use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use TheCodingMachine\TDBM\Bundle\TdbmBundle;
use function spl_object_hash;

class TdbmTestingKernel extends Kernel
{
    use MicroKernelTrait;

    const CONFIG_EXTS = '.{php,xml,yaml,yml}';
    /**
     * @var bool
     */
    private $multiDb;

    public function __construct(bool $multiDb = false)
    {
        parent::__construct('test', true);
        $this->multiDb = $multiDb;
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
                    'default_connection' => 'default',
                    'connections' => [
                        'default' => [
                            'driver' => 'pdo_mysql',
                            'server_version' => '5.7',
                            'charset'=> 'utf8mb4',
                            'default_table_options' => [
                                'charset' => 'utf8mb4',
                                'collate' => 'utf8mb4_unicode_ci',
                            ],
                            'url' => '%env(resolve:DATABASE_URL)%'
                        ],
                        'other' => [
                            'driver' => 'pdo_mysql',
                            'server_version' => '5.7',
                            'charset'=> 'utf8mb4',
                            'default_table_options' => [
                                'charset' => 'utf8mb4',
                                'collate' => 'utf8mb4_unicode_ci',
                            ],
                            'url' => '%env(resolve:DATABASE_URL2)%'
                        ],
                        'root' => [
                            'driver' => 'pdo_mysql',
                            'server_version' => '5.7',
                            'charset'=> 'utf8mb4',
                            'default_table_options' => [
                                'charset' => 'utf8mb4',
                                'collate' => 'utf8mb4_unicode_ci',
                            ],
                            'url' => '%env(resolve:DATABASE_URL_ROOT)%'
                        ],
                    ]

                ]
            ));

            if ($this->multiDb) {
                $container->loadFromExtension('tdbm', array(
                    'dao_namespace' => 'TheCodingMachine\TDBM\Bundle\Tests\GeneratedDb1\Daos',
                    'bean_namespace' => 'TheCodingMachine\TDBM\Bundle\Tests\GeneratedDb1\Beans',
                    'connection' => 'doctrine.dbal.default_connection',
                    'databases' => [
                        'other' => [
                            'dao_namespace' => 'TheCodingMachine\TDBM\Bundle\Tests\GeneratedDb2\Daos',
                            'bean_namespace' => 'TheCodingMachine\TDBM\Bundle\Tests\GeneratedDb2\Beans',
                            'connection' => 'doctrine.dbal.other_connection',
                        ]
                    ]
                ));

            }
        });
        $confDir = $this->getProjectDir().'/Tests/Fixtures/config';

        $loader->load($confDir.'/{packages}/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{packages}/'.$this->environment.'/**/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}_'.$this->environment.self::CONFIG_EXTS, 'glob');
    }

    public function getCacheDir()
    {
        return __DIR__.'/../cache/'.($this->multiDb?"multidb":"singledb").spl_object_hash($this);
    }
}
