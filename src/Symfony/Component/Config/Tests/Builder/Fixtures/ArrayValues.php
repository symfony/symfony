<?php

namespace Symfony\Component\Config\Tests\Builder\Fixtures;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ArrayValues implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('array_values');
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('transports')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(function (string $dsn) {
                                return ['dsn' => $dsn];
                            })
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('error_pages')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('with_trace')->end()
                    ->end()
                ->end()
            ->end();

        return $tb;
    }
}
