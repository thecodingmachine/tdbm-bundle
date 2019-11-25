<?php


namespace TheCodingMachine\TDBM\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('tdbm');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode('dao_namespace')->defaultValue('App\\Daos')->end()
            ->scalarNode('bean_namespace')->defaultValue('App\\Beans')->end()
            ->arrayNode('naming')
                ->children()
                    ->scalarNode('bean_prefix')->defaultValue('')->end()
                    ->scalarNode('bean_suffix')->defaultValue('')->end()
                    ->scalarNode('base_bean_prefix')->defaultValue('Abstract')->end()
                    ->scalarNode('base_bean_suffix')->defaultValue('')->end()
                    ->scalarNode('dao_prefix')->defaultValue('')->end()
                    ->scalarNode('dao_suffix')->defaultValue('Dao')->end()
                    ->scalarNode('base_dao_prefix')->defaultValue('Abstract')->end()
                    ->scalarNode('base_dao_suffix')->defaultValue('Dao')->end()
                    ->arrayNode('exceptions')
                        ->prototype('scalar')->end()
                ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
