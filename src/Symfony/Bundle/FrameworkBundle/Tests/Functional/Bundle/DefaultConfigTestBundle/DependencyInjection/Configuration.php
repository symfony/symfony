<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DefaultConfigTestBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('default_config_test');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('foo')->defaultValue('%default_config_test_foo%')->end()
                ->scalarNode('baz')->defaultValue('%env(BAZ)%')->end()
            ->end();

        return $treeBuilder;
    }
}
