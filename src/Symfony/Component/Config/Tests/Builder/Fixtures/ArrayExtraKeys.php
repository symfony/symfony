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

class ArrayExtraKeys implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('array_extra_keys');
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('foo')
                    ->ignoreExtraKeys(false)
                    ->children()
                        ->scalarNode('baz')->end()
                        ->scalarNode('qux')->end()
                    ->end()
                ->end()
                ->arrayNode('bar')
                    ->prototype('array')
                        ->ignoreExtraKeys(false)
                        ->children()
                            ->scalarNode('corge')->end()
                            ->scalarNode('grault')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('baz')
                    ->ignoreExtraKeys(false)
                ->end()
            ;

        return $tb;
    }
}
