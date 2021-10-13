<?php


namespace TheCodingMachine\TDBM\Bundle\Utils;


use Symfony\Contracts\Service\ServiceSubscriberInterface;
use TheCodingMachine\TDBM\ConfigurationInterface;
use TheCodingMachine\TDBM\Utils\BaseCodeGeneratorListener;
use TheCodingMachine\TDBM\Utils\BeanDescriptor;
use Laminas\Code\Generator\DocBlock\Tag\ReturnTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Generator\MethodGenerator;
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
        $method->setDocBlock(new DocBlockGenerator(
            null,
            null,
            [
                new ReturnTag([ 'array<string,string>' ])
            ]
        ));
        $method->setReturnType('array');
        $class->addMethodFromGenerator($method);

        return $fileGenerator;
    }
}
