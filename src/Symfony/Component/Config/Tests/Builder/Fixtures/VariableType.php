<?php

namespace Symfony\Component\Config\Tests\Builder\Fixtures;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class VariableType implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('variable_type');
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->variableNode('any_value')->end()
            ->end()
        ;

        return $tb;
    }
}
