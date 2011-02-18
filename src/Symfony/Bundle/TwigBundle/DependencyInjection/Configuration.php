<?php

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
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
     * @return \Symfony\Component\Config\Definition\NodeInterface
     */
    public function getConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('twig', 'array');

        $rootNode
            ->scalarNode('cache_warmer')->end()
        ;

        $this->addExtensionsSection($rootNode);
        $this->addFormSection($rootNode);
        $this->addGlobalsSection($rootNode);
        $this->addTwigOptions($rootNode);

        return $treeBuilder->buildTree();
    }

    private function addExtensionsSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->fixXmlConfig('extension')
            ->arrayNode('extensions')
                ->prototype('scalar')
                    ->beforeNormalization()
                        ->ifTrue(function($v) { return is_array($v) && isset($v['id']); })
                        ->then(function($v){ return $v['id']; })
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addFormSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('form')
                ->addDefaultsIfNotSet()
                ->fixXmlConfig('resource')
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->defaultValue(array('TwigBundle::form.html.twig'))
                    ->validate()
                        ->always()
                        ->then(function($v){
                            return array_merge(array('TwigBundle::form.html.twig'), $v);
                        })
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }

    private function addGlobalsSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->fixXmlConfig('global')
            ->arrayNode('globals')
                ->useAttributeAsKey('key')
                ->prototype('array')
                    ->beforeNormalization()
                        ->ifTrue(function($v){ return is_scalar($v); })
                        ->then(function($v){
                            return ('@' === substr($v, 0, 1))
                                   ? array('id' => substr($v, 1), 'type' => 'service')
                                   : array('value' => $v);
                        })
                    ->end()
                    ->scalarNode('id')->end()
                    ->scalarNode('type')
                        ->validate()
                            ->ifNotInArray(array('service'))
                            ->thenInvalid('The %s type is not supported')
                        ->end()
                    ->end()
                    ->scalarNode('value')->end()
                ->end()
            ->end()
        ;
    }

    private function addTwigOptions(NodeBuilder $rootNode)
    {
        $rootNode
            ->scalarNode('autoescape')->end()
            ->scalarNode('base_template_class')->end()
            ->scalarNode('cache')
                ->addDefaultsIfNotSet()
                ->defaultValue('%kernel.cache_dir%/twig')
            ->end()
            ->scalarNode('charset')
                ->addDefaultsIfNotSet()
                ->defaultValue('%kernel.charset%')
            ->end()
            ->scalarNode('debug')
                ->addDefaultsIfNotSet()
                ->defaultValue('%kernel.debug%')
            ->end()
            ->scalarNode('strict_variables')->end()
            ->scalarNode('auto_reload')->end()
        ;
    }
}
