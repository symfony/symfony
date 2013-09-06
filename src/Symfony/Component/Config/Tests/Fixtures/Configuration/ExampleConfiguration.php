<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Fixtures\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ExampleConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('root');

        $rootNode
            ->children()
                ->booleanNode('boolean')->defaultTrue()->end()
                ->scalarNode('scalar_empty')->end()
                ->scalarNode('scalar_null')->defaultNull()->end()
                ->scalarNode('scalar_true')->defaultTrue()->end()
                ->scalarNode('scalar_false')->defaultFalse()->end()
                ->scalarNode('scalar_default')->defaultValue('default')->end()
                ->scalarNode('scalar_array_empty')->defaultValue(array())->end()
                ->scalarNode('scalar_array_defaults')->defaultValue(array('elem1', 'elem2'))->end()
                ->arrayNode('array')
                    ->info('some info')
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('child1')->end()
                        ->scalarNode('child2')->end()
                        ->scalarNode('child3')
                            ->info(
                                "this is a long\n".
                                "multi-line info text\n".
                                "which should be indented"
                            )
                            ->example('example setting')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('array_prototype')
                    ->children()
                        ->arrayNode('parameters')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('value')->isRequired()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
