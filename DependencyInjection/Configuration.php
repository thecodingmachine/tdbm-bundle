<?php


namespace TheCodingMachine\TDBM\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tdbm');
        $rootNode = $treeBuilder->getRootNode();

        $rootNodeChildren = $rootNode->children();
        $this->buildServiceNode($rootNodeChildren);

        $rootNodeDatabases = $rootNodeChildren->arrayNode('databases')->arrayPrototype()->children();
        $this->buildServiceNode($rootNodeDatabases);

        return $treeBuilder;
    }

    private function buildServiceNode(NodeBuilder $serviceNode): void
    {
        $serviceNode->scalarNode('dao_namespace')->defaultValue('App\\Daos');
        $serviceNode->scalarNode('bean_namespace')->defaultValue('App\\Beans');
        $serviceNode->scalarNode('connection')->defaultValue('doctrine.dbal.default_connection');

        $namingNode = $serviceNode->arrayNode('naming')->addDefaultsIfNotSet()->children();
        $namingNode->scalarNode('bean_prefix')->defaultValue('');
        $namingNode->scalarNode('bean_suffix')->defaultValue('');
        $namingNode->scalarNode('base_bean_prefix')->defaultValue('Abstract');
        $namingNode->scalarNode('base_bean_suffix')->defaultValue('');
        $namingNode->scalarNode('dao_prefix')->defaultValue('');
        $namingNode->scalarNode('dao_suffix')->defaultValue('Dao');
        $namingNode->scalarNode('base_dao_prefix')->defaultValue('Abstract');
        $namingNode->scalarNode('base_dao_suffix')->defaultValue('Dao');
        $namingNode->arrayNode('exceptions')->prototype('scalar');
    }
}
