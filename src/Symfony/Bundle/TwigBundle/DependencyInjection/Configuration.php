<?php

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * TwigExtension configuration structure.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Configuration
{
    /**
     * Generates the configuration tree.
     *
     * @return \Symfony\Component\Config\Definition\ArrayNode The config tree
     */
    public function getConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('twig');

        $rootNode
            ->children()
                ->scalarNode('cache_warmer')->end()
            ->end();
        ;

        $this->addExtensionsSection($rootNode);
        $this->addFormSection($rootNode);
        $this->addGlobalsSection($rootNode);
        $this->addTwigOptions($rootNode);

        return $treeBuilder->buildTree();
    }

    private function addExtensionsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('extension')
            ->children()
                ->arrayNode('extensions')
                    ->prototype('scalar')
                        ->beforeNormalization()
                            ->ifTrue(function($v) { return is_array($v) && isset($v['id']); })
                            ->then(function($v){ return $v['id']; })
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
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
                            ->defaultValue(array('Twig::form.html.twig'))
                            ->validate()
                                ->always()
                                ->then(function($v){
                                    return array_merge(array('Twig::form.html.twig'), $v);
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
                            ->ifTrue(function($v){ return is_string($v) && '@' === substr($v, 0, 1); })
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
