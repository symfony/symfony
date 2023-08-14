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

class ScalarNormalizedTypes implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('scalar_normalized_types');
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('simple_array')
                    ->beforeNormalization()->ifString()->then(fn ($v) => [$v])->end()
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('keyed_array')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()->then(fn ($v) => [$v])
                        ->end()
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
                ->arrayNode('object')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => !\is_array($v))
                        ->then(fn ($v) => ['enabled' => $v])
                    ->end()
                    ->children()
                        ->booleanNode('enabled')->defaultNull()->end()
                        ->scalarNode('date_format')->end()
                        ->booleanNode('remove_used_context_fields')->end()
                    ->end()
                ->end()
                ->arrayNode('list_object')
                    ->beforeNormalization()
                        ->always()
                        ->then(function ($values) {
                            // inspired by Workflow places
                            if (isset($values[0]) && \is_string($values[0])) {
                                return array_map(function (string $value) {
                                    return ['name' => $value];
                                }, $values);
                            }

                            if (isset($values[0]) && \is_array($values[0])) {
                                return $values;
                            }

                            foreach ($values as $name => $value) {
                                if (\is_array($value) && \array_key_exists('name', $value)) {
                                    continue;
                                }
                                $value['name'] = $name;
                                $values[$name] = $value;
                            }

                            return array_values($values);
                        })
                    ->end()
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('data')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->prototype('variable')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('keyed_list_object')
                    ->useAttributeAsKey('class')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifTrue(fn ($v) => !\is_array($v))
                            ->then(fn ($v) => ['enabled' => $v])
                        ->end()
                        ->children()
                            ->booleanNode('enabled')->defaultTrue()->end()
                            ->arrayNode('settings')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('nested')
                    ->children()
                        ->arrayNode('nested_object')
                            ->addDefaultsIfNotSet()
                            ->beforeNormalization()
                                ->ifTrue(fn ($v) => !\is_array($v))
                                ->then(fn ($v) => ['enabled' => $v])
                            ->end()
                            ->children()
                                ->booleanNode('enabled')->defaultNull()->end()
                            ->end()
                        ->end()
                        ->arrayNode('nested_list_object')
                            ->beforeNormalization()
                                ->ifTrue(fn ($v) => isset($v[0]) && \is_string($v[0]))
                                ->then(function ($values) {
                                    return array_map(function (string $value) {
                                        return ['name' => $value];
                                    }, $values);
                                })
                            ->end()
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('name')
                                        ->isRequired()
                                        ->cannotBeEmpty()
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
