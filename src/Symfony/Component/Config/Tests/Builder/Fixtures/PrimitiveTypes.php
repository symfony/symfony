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
use Symfony\Component\Config\Tests\Fixtures\TestEnum;

class PrimitiveTypes implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('primitive_types');
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('boolean_node')->end()
                ->enumNode('enum_node')->values(['foo', 'bar', 'baz', TestEnum::Bar])->end()
                ->floatNode('float_node')->end()
                ->integerNode('integer_node')->end()
                ->scalarNode('scalar_node')->end()
                ->scalarNode('scalar_node_with_default')->defaultTrue()->end()
            ->end()
        ;

        return $tb;
    }
}
