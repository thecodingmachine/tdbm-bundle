<?php


namespace TheCodingMachine\TDBM\Bundle\Utils;


use Symfony\Contracts\Service\ServiceSubscriberInterface;
use TheCodingMachine\TDBM\ConfigurationInterface;
use TheCodingMachine\TDBM\Utils\BaseCodeGeneratorListener;
use TheCodingMachine\TDBM\Utils\BeanDescriptor;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use function var_export;

class SymfonyCodeGeneratorListener extends BaseCodeGeneratorListener
{
    /**
     * @param BeanDescriptor[] $beanDescriptors
     */
    public function onDaoFactoryGenerated(FileGenerator $fileGenerator, array $beanDescriptors, ConfigurationInterface $configuration): ?FileGenerator
    {
        $class = $fileGenerator->getClass();
        $class->setImplementedInterfaces([ ServiceSubscriberInterface::class ]);

        $getterBody = "return [\n";
        foreach ($beanDescriptors as $beanDescriptor) {
            $varExportClassName = var_export($configuration->getDaoNamespace().'\\'.$beanDescriptor->getDaoClassName(), true);
            $getterBody .= "    $varExportClassName => $varExportClassName,\n";
        }
        $getterBody .= "];\n";

        $method = new MethodGenerator(
            'getSubscribedServices',
            [],
            MethodGenerator::FLAG_PUBLIC,
            $getterBody
        );
        $method->setStatic(true);
        $method->setReturnType('void');
        $class->addMethodFromGenerator($method);

        return $fileGenerator;
    }
}
