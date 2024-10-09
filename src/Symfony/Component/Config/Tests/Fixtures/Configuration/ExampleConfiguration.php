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
use Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum;
use Symfony\Component\Config\Tests\Fixtures\TestEnum;

class ExampleConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('acme_root');

        $treeBuilder->getRootNode()
            ->fixXmlConfig('parameter')
            ->fixXmlConfig('connection')
            ->fixXmlConfig('cms_page')
            ->children()
                ->booleanNode('boolean')->defaultTrue()->end()
                ->scalarNode('scalar_empty')->end()
                ->scalarNode('scalar_null')->defaultNull()->end()
                ->scalarNode('scalar_true')->defaultTrue()->end()
                ->scalarNode('scalar_false')->defaultFalse()->end()
                ->scalarNode('scalar_default')->defaultValue('default')->end()
                ->scalarNode('scalar_array_empty')->defaultValue([])->end()
                ->scalarNode('scalar_array_defaults')->defaultValue(['elem1', 'elem2'])->end()
                ->scalarNode('scalar_required')->isRequired()->end()
                ->scalarNode('scalar_deprecated')->setDeprecated('vendor/package', '1.1')->end()
                ->scalarNode('scalar_deprecated_with_message')->setDeprecated('vendor/package', '1.1', 'Deprecation custom message for "%node%" at "%path%"')->end()
                ->scalarNode('node_with_a_looong_name')->end()
                ->enumNode('enum_with_default')->values(['this', 'that'])->defaultValue('this')->end()
                ->enumNode('enum')->values(['this', 'that', TestEnum::Ccc])->end()
                ->enumNode('enum_with_class')->enumFqcn(StringBackedTestEnum::class)->end()
                ->enumNode('unit_enum_with_class')->enumFqcn(TestEnum::class)->end()
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
                                'which should be indented'
                            )
                            ->example('example setting')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('scalar_prototyped')
                    ->prototype('scalar')->end()
                ->end()
                ->variableNode('variable')
                    ->example(['foo', 'bar'])
                ->end()
                ->arrayNode('parameters')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->info('Parameter name')->end()
                ->end()
                ->arrayNode('connections')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('user')->end()
                            ->scalarNode('pass')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cms_pages')
                    ->useAttributeAsKey('page')
                    ->prototype('array')
                        ->useAttributeAsKey('locale')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('title')->isRequired()->end()
                                ->scalarNode('path')->isRequired()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pipou')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('didou')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('array_with_array_example_and_no_default_value')
                    ->example(['foo', 'bar'])
                ->end()
                ->append(new CustomNodeDefinition('acme'))
            ->end()
        ;

        return $treeBuilder;
    }
}
