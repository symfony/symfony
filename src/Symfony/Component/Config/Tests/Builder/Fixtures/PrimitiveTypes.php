<?php

namespace Symfony\Component\Config\Tests\Builder\Fixtures;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class PrimitiveTypes implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('primitive_types');
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('boolean_node')->end()
                ->enumNode('enum_node')->values(['foo', 'bar', 'baz'])->end()
                ->floatNode('float_node')->end()
                ->integerNode('integer_node')->end()
                ->scalarNode('scalar_node')->end()
            ->end()
        ;

        return $tb;
    }
}
