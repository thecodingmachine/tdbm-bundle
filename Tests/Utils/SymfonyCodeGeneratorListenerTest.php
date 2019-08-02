<?php

namespace TheCodingMachine\TDBM\Bundle\Utils;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use TheCodingMachine\TDBM\Configuration;
use TheCodingMachine\TDBM\Utils\BeanDescriptor;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\FileGenerator;

class SymfonyCodeGeneratorListenerTest extends TestCase
{

    public function testOnDaoFactoryGenerated()
    {
        $file = new FileGenerator();
        $class = new ClassGenerator("Foo");
        $file->setClass($class);

        $codeGeneratorListener = new SymfonyCodeGeneratorListener();

        $beanDescriptor = $this->createMock(BeanDescriptor::class);
        $beanDescriptor->method('getDaoClassName')->willReturn('FooDao');
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getDaoNamespace')->willReturn('App\\Dao');

        $file = $codeGeneratorListener->onDaoFactoryGenerated($file, [$beanDescriptor], $configuration);

        $this->assertContains(ServiceSubscriberInterface::class, $file->getClass()->getImplementedInterfaces());
        $this->assertContains(<<<CODE
return [
    'App\\\\Dao\\\\FooDao' => 'App\\\\Dao\\\\FooDao',
];
CODE
            , $file->getClass()->getMethod('getSubscribedServices')->getBody());
    }
}
