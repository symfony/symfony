<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('framework');

        $rootNode
            ->children()
                ->scalarNode('secret')->end()
                ->scalarNode('http_method_override')
                    ->info("Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests.")
                    ->defaultTrue()
                ->end()
                ->arrayNode('trusted_proxies')
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return !is_array($v) && !is_null($v); })
                        ->then(function ($v) { return is_bool($v) ? array() : preg_split('/\s*,\s*/', $v); })
                    ->end()
                    ->prototype('scalar')
                        ->validate()
                            ->ifTrue(function ($v) {
                                if (empty($v)) {
                                    return false;
                                }

                                if (false !== strpos($v, '/')) {
                                    list($v, $mask) = explode('/', $v, 2);

                                    if (strcmp($mask, (int) $mask) || $mask < 1 || $mask > (false !== strpos($v, ':') ? 128 : 32)) {
                                        return true;
                                    }
                                }

                                return !filter_var($v, FILTER_VALIDATE_IP);
                            })
                            ->thenInvalid('Invalid proxy IP "%s"')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('ide')->defaultNull()->end()
                ->booleanNode('test')->end()
                ->scalarNode('default_locale')->defaultValue('en')->end()
                ->arrayNode('trusted_hosts')
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return is_string($v); })
                        ->then(function ($v) { return array($v); })
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;

        $this->addFormSection($rootNode);
        $this->addEsiSection($rootNode);
        $this->addFragmentsSection($rootNode);
        $this->addProfilerSection($rootNode);
        $this->addRouterSection($rootNode);
        $this->addSessionSection($rootNode);
        $this->addTemplatingSection($rootNode);
        $this->addTranslatorSection($rootNode);
        $this->addValidationSection($rootNode);
        $this->addAnnotationsSection($rootNode);
        $this->addSerializerSection($rootNode);

        return $treeBuilder;
    }

    private function addFormSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('form')
                    ->info('form configuration')
                    ->canBeEnabled()
                ->end()
                ->arrayNode('csrf_protection')
                    ->canBeDisabled()
                    ->children()
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
                    ->info('esi configuration')
                    ->canBeEnabled()
                ->end()
            ->end()
        ;
    }

    private function addFragmentsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('fragments')
                    ->info('fragments configuration')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('path')->defaultValue('/_fragment')->end()
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
                    ->info('profiler configuration')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('collect')->defaultTrue()->end()
                        ->booleanNode('only_exceptions')->defaultFalse()->end()
                        ->booleanNode('only_master_requests')->defaultFalse()->end()
                        ->scalarNode('dsn')->defaultValue('file:%kernel.cache_dir%/profiler')->end()
                        ->scalarNode('username')->defaultValue('')->end()
                        ->scalarNode('password')->defaultValue('')->end()
                        ->scalarNode('lifetime')->defaultValue(86400)->end()
                        ->arrayNode('matcher')
                            ->canBeUnset()
                            ->performNoDeepMerging()
                            ->children()
                                ->scalarNode('ip')->end()
                                ->scalarNode('path')
                                    ->info('use the urldecoded format')
                                    ->example('^/path to resource/')
                                ->end()
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
                    ->info('router configuration')
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('resource')->isRequired()->end()
                        ->scalarNode('type')->end()
                        ->scalarNode('http_port')->defaultValue(80)->end()
                        ->scalarNode('https_port')->defaultValue(443)->end()
                        ->scalarNode('strict_requirements')
                            ->info(
                                "set to true to throw an exception when a parameter does not match the requirements\n".
                                "set to false to disable exceptions when a parameter does not match the requirements (and return null instead)\n".
                                "set to null to disable parameter checks against requirements\n".
                                "'true' is the preferred configuration in development mode, while 'false' or 'null' might be preferred in production"
                            )
                            ->defaultTrue()
                        ->end()
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
                    ->info('session configuration')
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('storage_id')->defaultValue('session.storage.native')->end()
                        ->scalarNode('handler_id')->defaultValue('session.handler.native_file')->end()
                        ->scalarNode('name')->end()
                        ->scalarNode('cookie_lifetime')->end()
                        ->scalarNode('cookie_path')->end()
                        ->scalarNode('cookie_domain')->end()
                        ->booleanNode('cookie_secure')->end()
                        ->booleanNode('cookie_httponly')->end()
                        ->scalarNode('gc_divisor')->end()
                        ->scalarNode('gc_probability')->end()
                        ->scalarNode('gc_maxlifetime')->end()
                        ->scalarNode('save_path')->defaultValue('%kernel.cache_dir%/sessions')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addTemplatingSection(ArrayNodeDefinition $rootNode)
    {
        $organizeUrls = function ($urls) {
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
                    ->info('templating configuration')
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('assets_version')->defaultValue(null)->end()
                        ->scalarNode('assets_version_format')->defaultValue('%%s?%%s')->end()
                        ->scalarNode('hinclude_default_template')->defaultNull()->end()
                        ->arrayNode('form')
                            ->addDefaultsIfNotSet()
                            ->fixXmlConfig('resource')
                            ->children()
                                ->arrayNode('resources')
                                    ->addDefaultChildrenIfNoneSet()
                                    ->prototype('scalar')->defaultValue('FrameworkBundle:Form')->end()
                                    ->validate()
                                        ->ifTrue(function ($v) {return !in_array('FrameworkBundle:Form', $v); })
                                        ->then(function ($v) {
                                            return array_merge(array('FrameworkBundle:Form'), $v);
                                        })
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->fixXmlConfig('assets_base_url')
                    ->children()
                        ->arrayNode('assets_base_urls')
                            ->performNoDeepMerging()
                            ->addDefaultsIfNotSet()
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return !is_array($v); })
                                ->then(function ($v) { return array($v); })
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
                            ->example(array('twig'))
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return !is_array($v); })
                                ->then(function ($v) { return array($v); })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                    ->fixXmlConfig('loader')
                    ->children()
                        ->arrayNode('loaders')
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return !is_array($v); })
                                ->then(function ($v) { return array($v); })
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
                                    ->scalarNode('version_format')->defaultValue('%%s?%%s')->end()
                                    ->arrayNode('base_urls')
                                        ->performNoDeepMerging()
                                        ->addDefaultsIfNotSet()
                                        ->beforeNormalization()
                                            ->ifTrue(function ($v) { return !is_array($v); })
                                            ->then(function ($v) { return array($v); })
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
                    ->info('translator configuration')
                    ->canBeEnabled()
                    ->children()
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
                    ->info('validation configuration')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('cache')->end()
                        ->booleanNode('enable_annotations')->defaultFalse()->end()
                        ->scalarNode('translation_domain')->defaultValue('validators')->end()
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
                    ->info('annotation configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cache')->defaultValue('file')->end()
                        ->scalarNode('file_cache_dir')->defaultValue('%kernel.cache_dir%/annotations')->end()
                        ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addSerializerSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('serializer')
                    ->info('serializer configuration')
                    ->canBeEnabled()
                ->end()
            ->end()
        ;
    }
}
