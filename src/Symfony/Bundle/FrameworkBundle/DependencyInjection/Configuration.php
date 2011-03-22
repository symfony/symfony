<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * FrameworkExtension configuration structure.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Configuration
{
    /**
     * Generates the configuration tree.
     *
     * @param boolean $kernelDebug The kernel.debug DIC parameter
     *
     * @return \Symfony\Component\Config\Definition\ArrayNode The config tree
     */
    public function getConfigTree($kernelDebug)
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('framework');

        $rootNode
            ->children()
                ->scalarNode('cache_warmer')->defaultValue(!$kernelDebug)->end()
                ->scalarNode('charset')->end()
                ->scalarNode('document_root')->end()
                ->scalarNode('error_handler')->end()
                ->scalarNode('exception_controller')->end()
                ->scalarNode('ide')->end()
                ->booleanNode('test')->end()
            ->end()
        ;

        $this->addCsrfProtectionSection($rootNode);
        $this->addEsiSection($rootNode);
        $this->addProfilerSection($rootNode);
        $this->addRouterSection($rootNode);
        $this->addSessionSection($rootNode);
        $this->addTemplatingSection($rootNode);
        $this->addTranslatorSection($rootNode);
        $this->addValidationSection($rootNode);

        return $treeBuilder->buildTree();
    }

    private function addCsrfProtectionSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('csrf_protection')
                    ->canBeUnset()
                    ->treatNullLike(array('enabled' => true))
                    ->treatTrueLike(array('enabled' => true))
                    ->children()
                        ->booleanNode('enabled')->end()
                        ->scalarNode('field_name')->end()
                        ->scalarNode('secret')->end()
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
                        ->booleanNode('enabled')->end()
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
                        ->booleanNode('only_exceptions')->defaultValue(false)->end()
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
                        ->scalarNode('cache_warmer')->defaultFalse()->end()
                        ->scalarNode('resource')->isRequired()->end()
                        ->scalarNode('type')->end()
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
                    // Strip "pdo." prefix from option keys, since dots cannot appear in node names
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(function($v){
                            foreach ($v as $key => $value) {
                                if (0 === strncmp('pdo.', $key, 4)) {
                                    $v[substr($key, 4)] = $value;
                                    unset($v[$key]);
                                }
                            }
                            return $v;
                        })
                    ->end()                    
                    ->children()
                        ->booleanNode('auto_start')->end()
                        ->scalarNode('class')->end()
                        ->scalarNode('default_locale')->end()
                        ->scalarNode('storage_id')->defaultValue('native')->end()
                        // NativeSessionStorage options
                        ->scalarNode('name')->end()
                        ->scalarNode('lifetime')->end()
                        ->scalarNode('path')->end()
                        ->scalarNode('domain')->end()
                        ->booleanNode('secure')->end()
                        ->booleanNode('httponly')->end()
                        // PdoSessionStorage options
                        ->scalarNode('db_table')->end()
                        ->scalarNode('db_id_col')->end()
                        ->scalarNode('db_data_col')->end()
                        ->scalarNode('db_time_col')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addTemplatingSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('templating')
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('assets_version')->end()
                    ->end()
                    ->fixXmlConfig('assets_base_url')
                    ->children()
                        ->arrayNode('assets_base_urls')
                            ->beforeNormalization()
                                ->ifTrue(function($v){ return !is_array($v); })
                                ->then(function($v){ return array($v); })
                            ->end()
                            ->prototype('scalar')
                                ->beforeNormalization()
                                    ->ifTrue(function($v) { return is_array($v) && isset($v['value']); })
                                    ->then(function($v){ return $v['value']; })
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('cache')->end()
                        ->scalarNode('cache_warmer')->defaultFalse()->end()
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
                            ->prototype('scalar')
                                ->beforeNormalization()
                                    ->ifTrue(function($v) { return is_array($v) && isset($v['id']); })
                                    ->then(function($v){ return $v['id']; })
                                ->end()
                            ->end()
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
                                ->children()
                                    ->scalarNode('version')->defaultNull()->end()
                                ->end()
                                ->fixXmlConfig('base_url')
                                ->children()
                                    ->arrayNode('base_urls')
                                        ->prototype('scalar')
                                            ->beforeNormalization()
                                                ->ifTrue(function($v) { return is_array($v) && isset($v['value']); })
                                                ->then(function($v){ return $v['value']; })
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
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('fallback')->end()
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
                    // For XML, namespace is a child of validation, so it must be moved under annotations
                    ->beforeNormalization()
                        ->ifTrue(function($v) { return is_array($v) && !empty($v['annotations']) && !empty($v['namespace']); })
                        ->then(function($v){
                            $v['annotations'] = array('namespace' => $v['namespace']);
                            unset($v['namespace']);
                            return $v;
                        })
                    ->end()
                    ->children()
                        ->booleanNode('enabled')->end()
                        ->arrayNode('annotations')
                            ->canBeUnset()
                            ->treatNullLike(array())
                            ->treatTrueLike(array())
                            ->fixXmlConfig('namespace')
                            ->children()
                                ->arrayNode('namespaces')
                                    ->useAttributeAsKey('prefix')
                                    ->prototype('scalar')
                                        ->beforeNormalization()
                                            ->ifTrue(function($v) { return is_array($v) && isset($v['namespace']); })
                                            ->then(function($v){ return $v['namespace']; })
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
}
