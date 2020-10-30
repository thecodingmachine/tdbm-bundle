<?php

namespace TheCodingMachine\TDBM\Bundle\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use TheCodingMachine\FluidSchema\TdbmFluidSchema;
use TheCodingMachine\TDBM\Bundle\DependencyInjection\Configuration;
use TheCodingMachine\TDBM\TDBMService;
use Throwable;

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

    public function testEndToEnd(): void
    {
        $kernel = new TdbmTestingKernel(true);
        $kernel->boot();
        $container = $kernel->getContainer();

        /**
         * @var Connection $connectionRoot
         */
        $connectionRoot = $container->get('doctrine.dbal.root_connection');

        $connectionRoot->getSchemaManager()->dropAndCreateDatabase('test_tdbmbundle');
        $connectionRoot->getSchemaManager()->dropAndCreateDatabase('test_tdbmbundle2');

        /**
         * @var Connection $connection1
         */
        $connection1 = $container->get('doctrine.dbal.default_connection');

        $fromSchema1 = $connection1->getSchemaManager()->createSchema();
        $toSchema1 = clone $fromSchema1;

        $db = new TdbmFluidSchema($toSchema1, new \TheCodingMachine\FluidSchema\DefaultNamingStrategy($connection1->getDatabasePlatform()));

        $db->table('country')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('label')->string(255)->unique();

        $sqlStmts = $toSchema1->getMigrateFromSql($fromSchema1, $connection1->getDatabasePlatform());

        foreach ($sqlStmts as $sqlStmt) {
            //echo $sqlStmt."\n";
            $connection1->executeStatement($sqlStmt);
        }


        /**
         * @var Connection $connection2
         */
        $connection2 = $container->get('doctrine.dbal.other_connection');

        $fromSchema2 = $connection2->getSchemaManager()->createSchema();
        $toSchema2 = clone $fromSchema2;

        $db = new TdbmFluidSchema($toSchema2, new \TheCodingMachine\FluidSchema\DefaultNamingStrategy($connection2->getDatabasePlatform()));

        $db->table('person')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('name')->string(255);

        $sqlStmts = $toSchema2->getMigrateFromSql($fromSchema2, $connection2->getDatabasePlatform());

        foreach ($sqlStmts as $sqlStmt) {
            //echo $sqlStmt."\n";
            $connection2->executeStatement($sqlStmt);
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $applicationTester = new ApplicationTester($application);
        $applicationTester->run(['command' => 'tdbm:generate']);
        $this->assertStringContainsString('Finished regenerating DAOs and beans', $applicationTester->getDisplay());
        $this->assertFileExists(__DIR__.'/../tdbm.lock.yml');

        $applicationTester = new ApplicationTester($application);
        $applicationTester->run(['command' => 'tdbm:generate:other']);
        $this->assertStringContainsString('Finished regenerating DAOs and beans', $applicationTester->getDisplay());
        $this->assertFileExists(__DIR__.'/../tdbm.other.lock.yml');
    }
}
