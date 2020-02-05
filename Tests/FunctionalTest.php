<?php

namespace TheCodingMachine\TDBM\Bundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use TheCodingMachine\TDBM\TDBMService;

class FunctionalTest extends TestCase
{
    public function testServiceWiring()
    {
        $kernel = new TdbmTestingKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();

        $tdbmService = $container->get(TDBMService::class);
        $this->assertInstanceOf(TDBMService::class, $tdbmService);
    }
}
