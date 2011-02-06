<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Configuration\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Configuration\Builder\TreeBuilder;

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
     * @return \Symfony\Component\DependencyInjection\Configuration\NodeInterface
     */
    public function getConfigTree($kernelDebug)
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('app:config', 'array');

        $rootNode
            ->scalarNode('cache_warmer')->defaultValue(!$kernelDebug)->end()
            ->scalarNode('charset')->end()
            ->scalarNode('document_root')->end()
            ->scalarNode('error_handler')->end()
            ->scalarNode('exception_controller')->end()
            ->scalarNode('ide')->end()
            ->booleanNode('test')->end()
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

    private function addCsrfProtectionSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('csrf_protection')
                ->canBeUnset()
                ->treatNullLike(array('enabled' => true))
                ->treatTrueLike(array('enabled' => true))
                ->booleanNode('enabled')->end()
                ->scalarNode('field_name')->end()
                ->scalarNode('secret')->end()
                ->end()
        ;
    }

    private function addEsiSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('esi')
                ->canBeUnset()
                ->treatNullLike(array('enabled' => true))
                ->treatTrueLike(array('enabled' => true))
                ->booleanNode('enabled')->end()
                ->end()
        ;
    }

    private function addProfilerSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('profiler')
                ->canBeUnset()
                ->treatNullLike(array())
                ->treatTrueLike(array())
                ->booleanNode('only_exceptions')->end()
                ->arrayNode('matcher')
                    ->canBeUnset()
                    ->scalarNode('ip')->end()
                    ->scalarNode('path')->end()
                    ->scalarNode('service')->end()
                    ->end()
                ->end()
        ;
    }

    private function addRouterSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('router')
                ->canBeUnset()
                ->scalarNode('cache_warmer')->end()
                ->scalarNode('resource')->isRequired()->end()
                ->end()
        ;
    }

    private function addSessionSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('session')
                ->canBeUnset()
                ->treatNullLike(array())
                ->treatTrueLike(array())
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
        ;
    }

    private function addTemplatingSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('templating')
                ->canBeUnset()
                ->scalarNode('assets_version')->end()
                ->scalarNode('assets_base_urls')->end()
                ->scalarNode('cache')->end()
                ->scalarNode('cache_warmer')->end()
                ->fixXmlConfig('engine')
                ->arrayNode('engines')
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
                ->fixXmlConfig('loader')
                ->arrayNode('loaders')
                    ->beforeNormalization()
                        ->ifTrue(function($v){ return !is_array($v); })
                        ->then(function($v){ return array($v); })
                        ->end()
                    ->prototype('scalar')->end()
                    ->end()
                ->end()
        ;
    }

    private function addTranslatorSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('translator')
                ->canBeUnset()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('fallback')->end()
                ->end()
        ;
    }

    private function addValidationSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('validation')
                ->canBeUnset()
                // For XML, namespace is a child of validation, so it must be moved under annotations
                ->beforeNormalization()
                    ->ifTrue(function($v) { return is_array($v) && !empty($v['annotations']) && !empty($v['namespace']); })
                    ->then(function($v){
                        $v['annotations'] = array('namespace' => $v['namespace']);
                        return $v;
                    })
                    ->end()
                ->booleanNode('enabled')->end()
                ->arrayNode('annotations')
                    ->canBeUnset()
                    ->treatNullLike(array())
                    ->treatTrueLike(array())
                    ->fixXmlConfig('namespace')
                    ->arrayNode('namespaces')
                        ->containsNameValuePairsWithKeyAttribute('prefix')
                        ->prototype('scalar')
                            ->beforeNormalization()
                                ->ifTrue(function($v) { return is_array($v) && isset($v['namespace']); })
                                ->then(function($v){ return $v['namespace']; })
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
        ;
    }
}
