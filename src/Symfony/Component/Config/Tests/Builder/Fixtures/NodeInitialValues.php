<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Builder\Fixtures;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class NodeInitialValues implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('node_initial_values');
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('some_clever_name')
                    ->children()
                        ->scalarNode('first')->end()
                        ->scalarNode('second')->end()
                        ->scalarNode('third')->end()
                    ->end()
                ->end()

                ->arrayNode('messenger')
                    ->children()
                        ->arrayNode('transports')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->fixXmlConfig('option')
                                ->children()
                                    ->scalarNode('dsn')->end()
                                    ->scalarNode('serializer')->defaultNull()->end()
                                    ->arrayNode('options')
                                        ->normalizeKeys(false)
                                        ->defaultValue([])
                                        ->prototype('variable')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ;

        return $tb;
    }
}
