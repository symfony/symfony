<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * FrameworkExtension configuration structure.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    private $debug;

    /**
     * Constructor
     *
     * @param Boolean $debug Whether to use the debug mode
     */
    public function  __construct($debug)
    {
        $this->debug = (Boolean) $debug;
    }

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('framework');

        $rootNode
            ->children()
                ->scalarNode('charset')->end()
                ->scalarNode('trust_proxy_headers')->defaultFalse()->end()
                ->scalarNode('secret')->isRequired()->end()
                ->scalarNode('ide')->defaultNull()->end()
                ->booleanNode('test')->end()
            ->end()
        ;

        $this->addFormSection($rootNode);
        $this->addEsiSection($rootNode);
        $this->addProfilerSection($rootNode);
        $this->addRouterSection($rootNode);
        $this->addSessionSection($rootNode);
        $this->addTemplatingSection($rootNode);
        $this->addTranslatorSection($rootNode);
        $this->addValidationSection($rootNode);
        $this->addAnnotationsSection($rootNode);

        return $treeBuilder;
    }

    private function addFormSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('form')
                    ->canBeUnset()
                    ->treatNullLike(array('enabled' => true))
                    ->treatTrueLike(array('enabled' => true))
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('csrf_protection')
                    ->canBeUnset()
                    ->treatNullLike(array('enabled' => true))
                    ->treatTrueLike(array('enabled' => true))
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('field_name')->defaultValue('_token')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addEsiSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('esi')
                    ->canBeUnset()
                    ->treatNullLike(array('enabled' => true))
                    ->treatTrueLike(array('enabled' => true))
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addProfilerSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('profiler')
                    ->canBeUnset()
                    ->children()
                        ->booleanNode('only_exceptions')->defaultFalse()->end()
                        ->booleanNode('only_master_requests')->defaultFalse()->end()
                        ->scalarNode('dsn')->defaultValue('sqlite:%kernel.cache_dir%/profiler.db')->end()
                        ->scalarNode('username')->defaultValue('')->end()
                        ->scalarNode('password')->defaultValue('')->end()
                        ->scalarNode('lifetime')->defaultValue(86400)->end()
                        ->arrayNode('matcher')
                            ->canBeUnset()
                            ->performNoDeepMerging()
                            ->children()
                                ->scalarNode('ip')->end()
                                ->scalarNode('path')->end()
                                ->scalarNode('service')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addRouterSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('router')
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('resource')->isRequired()->end()
                        ->scalarNode('type')->end()
                        ->scalarNode('http_port')->defaultValue(80)->end()
                        ->scalarNode('https_port')->defaultValue(443)->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addSessionSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('session')
                    ->canBeUnset()
                    ->children()
                        ->booleanNode('auto_start')->defaultFalse()->end()
                        ->scalarNode('default_locale')->defaultValue('en')->end()
                        ->scalarNode('storage_id')->defaultValue('session.storage.native')->end()
                        ->scalarNode('name')->end()
                        ->scalarNode('lifetime')->end()
                        ->scalarNode('path')->end()
                        ->scalarNode('domain')->end()
                        ->booleanNode('secure')->end()
                        ->booleanNode('httponly')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addTemplatingSection(ArrayNodeDefinition $rootNode)
    {
        $organizeUrls = function($urls) {
            $urls += array(
                'http' => array(),
                'ssl'  => array(),
            );

            foreach ($urls as $i => $url) {
                if (is_integer($i)) {
                    if (0 === strpos($url, 'https://') || 0 === strpos($url, '//')) {
                        $urls['http'][] = $urls['ssl'][] = $url;
                    } else {
                        $urls['http'][] = $url;
                    }
                    unset($urls[$i]);
                }
            }

            return $urls;
        };

        $rootNode
            ->children()
                ->arrayNode('templating')
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('assets_version')->defaultValue(null)->end()
                        ->scalarNode('assets_version_format')->defaultValue(null)->end()
                        ->arrayNode('form')
                            ->addDefaultsIfNotSet()
                            ->fixXmlConfig('resource')
                            ->children()
                                ->arrayNode('resources')
                                    ->addDefaultsIfNotSet()
                                    ->defaultValue(array('FrameworkBundle:Form'))
                                    ->validate()
                                        ->ifTrue(function($v) {return !in_array('FrameworkBundle:Form', $v); })
                                        ->then(function($v){
                                            return array_merge(array('FrameworkBundle:Form'), $v);
                                        })
                                    ->end()
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->fixXmlConfig('assets_base_url')
                    ->children()
                        ->arrayNode('assets_base_urls')
                            ->addDefaultsIfNotSet()
                            ->defaultValue(array('http' => array(), 'ssl' => array()))
                            ->beforeNormalization()
                                ->ifTrue(function($v) { return !is_array($v); })
                                ->then(function($v) { return array($v); })
                            ->end()
                            ->beforeNormalization()
                                ->always()
                                ->then($organizeUrls)
                            ->end()
                            ->children()
                                ->arrayNode('http')
                                    ->prototype('scalar')->end()
                                ->end()
                                ->arrayNode('ssl')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('cache')->end()
                    ->end()
                    ->fixXmlConfig('engine')
                    ->children()
                        ->arrayNode('engines')
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->beforeNormalization()
                                ->ifTrue(function($v){ return !is_array($v); })
                                ->then(function($v){ return array($v); })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                    ->fixXmlConfig('loader')
                    ->children()
                        ->arrayNode('loaders')
                            ->beforeNormalization()
                                ->ifTrue(function($v){ return !is_array($v); })
                                ->then(function($v){ return array($v); })
                             ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                    ->fixXmlConfig('package')
                    ->children()
                        ->arrayNode('packages')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->fixXmlConfig('base_url')
                                ->children()
                                    ->scalarNode('version')->defaultNull()->end()
                                    ->scalarNode('version_format')->defaultNull()->end()
                                    ->arrayNode('base_urls')
                                        ->addDefaultsIfNotSet()
                                        ->defaultValue(array('http' => array(), 'ssl' => array()))
                                        ->beforeNormalization()
                                            ->ifTrue(function($v) { return !is_array($v); })
                                            ->then(function($v) { return array($v); })
                                        ->end()
                                        ->beforeNormalization()
                                            ->always()
                                            ->then($organizeUrls)
                                        ->end()
                                        ->children()
                                            ->arrayNode('http')
                                                ->prototype('scalar')->end()
                                            ->end()
                                            ->arrayNode('ssl')
                                                ->prototype('scalar')->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addTranslatorSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('translator')
                    ->canBeUnset()
                    ->treatNullLike(array('enabled' => true))
                    ->treatTrueLike(array('enabled' => true))
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('fallback')->defaultValue('en')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addValidationSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('validation')
                    ->canBeUnset()
                    ->treatNullLike(array('enabled' => true))
                    ->treatTrueLike(array('enabled' => true))
                    ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('cache')->end()
                        ->booleanNode('enable_annotations')->defaultFalse()->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addAnnotationsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('annotations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cache')->defaultValue('file')->end()
                        ->scalarNode('file_cache_dir')->defaultValue('%kernel.cache_dir%/annotations')->end()
                        ->booleanNode('debug')->defaultValue($this->debug)->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
