<?php


namespace TheCodingMachine\TDBM\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('tdbm');

        $rootNode
            ->children()
            ->scalarNode('dao_namespace')->end()
            ->scalarNode('bean_namespace')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
