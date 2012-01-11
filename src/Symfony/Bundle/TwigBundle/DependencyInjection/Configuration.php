<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * TwigExtension configuration structure.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('twig');

        $rootNode
            ->children()
                ->scalarNode('exception_controller')->defaultValue('Symfony\\Bundle\\TwigBundle\\Controller\\ExceptionController::showAction')->end()
            ->end()
        ;

        $this->addFormSection($rootNode);
        $this->addGlobalsSection($rootNode);
        $this->addTwigOptions($rootNode);

        return $treeBuilder;
    }

    private function addFormSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('form')
                    ->addDefaultsIfNotSet()
                    ->fixXmlConfig('resource')
                    ->children()
                        ->arrayNode('resources')
                            ->addDefaultsIfNotSet()
                            ->defaultValue(array('form_div_layout.html.twig'))
                            ->validate()
                                ->ifTrue(function($v) { return !in_array('form_div_layout.html.twig', $v); })
                                ->then(function($v){
                                    return array_merge(array('form_div_layout.html.twig'), $v);
                                })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addGlobalsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('global')
            ->children()
                ->arrayNode('globals')
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifTrue(function($v){ return is_string($v) && 0 === strpos($v, '@'); })
                            ->then(function($v){ return array('id' => substr($v, 1), 'type' => 'service'); })
                        ->end()
                        ->beforeNormalization()
                            ->ifTrue(function($v){
                                if (is_array($v)) {
                                    $keys = array_keys($v);
                                    sort($keys);

                                    return $keys !== array('id', 'type') && $keys !== array('value');
                                }

                                return true;
                            })
                            ->then(function($v){ return array('value' => $v); })
                        ->end()
                        ->children()
                            ->scalarNode('id')->end()
                            ->scalarNode('type')
                                ->validate()
                                    ->ifNotInArray(array('service'))
                                    ->thenInvalid('The %s type is not supported')
                                ->end()
                            ->end()
                            ->variableNode('value')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addTwigOptions(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->scalarNode('autoescape')->end()
                ->scalarNode('base_template_class')->end()
                ->scalarNode('cache')->defaultValue('%kernel.cache_dir%/twig')->end()
                ->scalarNode('charset')->defaultValue('%kernel.charset%')->end()
                ->scalarNode('debug')->defaultValue('%kernel.debug%')->end()
                ->scalarNode('strict_variables')->end()
                ->scalarNode('auto_reload')->end()
            ->end()
        ;
    }
}
