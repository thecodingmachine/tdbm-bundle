<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Bundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TdbmCompilerPass implements CompilerPassInterface
{

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        $generatorListeners = $this->taggedServicesToReferences($container->findTaggedServiceIds(TdbmExtension::TAG_GENERATOR_LISTENER));
        $codeGeneratorListeners = $this->taggedServicesToReferences($container->findTaggedServiceIds(TdbmExtension::TAG_CODE_GENERATOR_LISTENER));

        $tdbmConfigurations = array_keys($container->findTaggedServiceIds(TdbmExtension::TAG_TDBM_CONFIGURATION));

        foreach ($tdbmConfigurations as $tdbmConfiguration) {
            $configuration = $container->getDefinition($tdbmConfiguration);
            $configuration->setArgument('$generatorListeners', $generatorListeners);
            $configuration->setArgument('$codeGeneratorListeners', $codeGeneratorListeners);
        }
    }

    /**
     * @param array<string, mixed[]> $taggedServices Keys are services ids, this is the output of `ContainerBuilder::findTaggedServiceIds`
     * @return array<Reference>
     */
    private function taggedServicesToReferences(array $taggedServices): array
    {
        return array_map(static function (string $serviceId) {
            return new Reference($serviceId);
        }, array_keys($taggedServices));
    }
}
