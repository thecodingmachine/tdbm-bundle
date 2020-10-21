<?php

namespace TheCodingMachine\TDBM\Bundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use TheCodingMachine\TDBM\Bundle\DependencyInjection\Configuration;
use TheCodingMachine\TDBM\TDBMService;

class FunctionalTest extends TestCase
{
    private const DEFAULT_CONFIGURATION = [
        'dao_namespace' => 'App\Daos',
        'bean_namespace' => 'App\Beans',
        'connection' => 'doctrine.dbal.default_connection',
        'naming' => [
            'bean_prefix' => '',
            'bean_suffix' => '',
            'base_bean_prefix' => 'Abstract',
            'base_bean_suffix' => '',
            'dao_prefix' => '',
            'dao_suffix' => 'Dao',
            'base_dao_prefix' => 'Abstract',
            'base_dao_suffix' => 'Dao',
            'exceptions' => [],
        ],
    ];

    public function testServiceWiring(): void
    {
        $kernel = new TdbmTestingKernel();
        $kernel->boot();
        $container = $kernel->getContainer();

        $tdbmService = $container->get(TDBMService::class);
        $this->assertInstanceOf(TDBMService::class, $tdbmService);
    }

    public function testDefaultConfiguration(): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $expected = self::DEFAULT_CONFIGURATION;
        $expected['databases'] = [];

        $this->assertEquals($expected, $processor->processConfiguration($configuration, []));
    }

    public function testTwoDatabasesConfiguration(): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $expected = self::DEFAULT_CONFIGURATION;
        $expected['databases'] = ['test1' => self::DEFAULT_CONFIGURATION, 'test2' => self::DEFAULT_CONFIGURATION];

        $this->assertEquals(
            $expected,
            $processor->processConfiguration($configuration, [['databases' => ['test1' => [], 'test2' => []]]])
        );
    }

    public function testExceptionsConfiguration(): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $expected = self::DEFAULT_CONFIGURATION;
        $expected['databases'] = [];
        $expected['naming']['exceptions'] = ['object' => 'AnObject'];

        $this->assertEquals($expected, $processor->processConfiguration($configuration, [['naming' => ['exceptions' => ['object' => 'AnObject']]]]));
    }
}
