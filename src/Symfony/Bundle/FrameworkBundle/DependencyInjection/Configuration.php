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

use Doctrine\Common\Annotations\Annotation;
use Symfony\Bundle\FullStack;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;

/**
 * FrameworkExtension configuration structure.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    private $debug;

    /**
     * @param bool $debug Whether debugging is enabled or not
     */
    public function __construct($debug)
    {
        $this->debug = (bool) $debug;
    }

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
            ->beforeNormalization()
                ->ifTrue(function ($v) { return !isset($v['assets']) && isset($v['templating']); })
                ->then(function ($v) {
                    $v['assets'] = array();

                    return $v;
                })
            ->end()
            ->children()
                ->scalarNode('secret')
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#secret')
                ->end()
                ->scalarNode('http_method_override')
                    ->info("Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests. Note: When using the HttpCache, you need to call the method in your front controller instead")
                    ->defaultTrue()
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#http_method_override')
                ->end()
                ->arrayNode('trusted_proxies')
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return !is_array($v) && null !== $v; })
                        ->then(function ($v) { return is_bool($v) ? array() : preg_split('/\s*,\s*/', $v); })
                    ->end()
                    ->prototype('scalar')
                        ->validate()
                            ->ifTrue(function ($v) {
                                if (empty($v)) {
                                    return false;
                                }

                                if (false !== strpos($v, '/')) {
                                    if ('0.0.0.0/0' === $v) {
                                        return false;
                                    }

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
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#trusted_proxies')
                ->end()
                ->scalarNode('ide')
                    ->defaultNull()
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#ide')
                ->end()
                ->booleanNode('test')
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#test')
                ->end()
                ->scalarNode('default_locale')
                    ->defaultValue('en')
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#default_locale')
                ->end()
                ->arrayNode('trusted_hosts')
                    ->beforeNormalization()->ifString()->then(function ($v) { return array($v); })->end()
                    ->prototype('scalar')->end()
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#trusted_hosts')
                ->end()
            ->end()
        ;

        $this->addCsrfSection($rootNode);
        $this->addFormSection($rootNode);
        $this->addEsiSection($rootNode);
        $this->addSsiSection($rootNode);
        $this->addFragmentsSection($rootNode);
        $this->addProfilerSection($rootNode);
        $this->addWorkflowSection($rootNode);
        $this->addRouterSection($rootNode);
        $this->addSessionSection($rootNode);
        $this->addRequestSection($rootNode);
        $this->addTemplatingSection($rootNode);
        $this->addAssetsSection($rootNode);
        $this->addTranslatorSection($rootNode);
        $this->addValidationSection($rootNode);
        $this->addAnnotationsSection($rootNode);
        $this->addSerializerSection($rootNode);
        $this->addPropertyAccessSection($rootNode);
        $this->addPropertyInfoSection($rootNode);
        $this->addCacheSection($rootNode);
        $this->addPhpErrorsSection($rootNode);

        return $treeBuilder;
    }

    private function addCsrfSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('csrf_protection')
                    ->canBeEnabled()
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#csrf_protection')
                ->end()
            ->end()
        ;
    }

    private function addFormSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('form')
                    ->info('form configuration')
                    ->{!class_exists(FullStack::class) && class_exists(Form::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->children()
                        ->arrayNode('csrf_protection')
                            ->treatFalseLike(array('enabled' => false))
                            ->treatTrueLike(array('enabled' => true))
                            ->treatNullLike(array('enabled' => true))
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultNull() // defaults to framework.csrf_protection.enabled
                                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-csrf-protection-enabled')
                                ->end()
                                ->scalarNode('field_name')->defaultValue('_token')->end()
                            ->end()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#csrf_protection')
                        ->end()
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
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#esi')
                ->end()
            ->end()
        ;
    }

    private function addSsiSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('ssi')
                    ->info('ssi configuration')
                    ->canBeEnabled()
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#ssi')
                ->end()
            ->end();
    }

    private function addFragmentsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('fragments')
                    ->info('fragments configuration')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('path')
                            ->defaultValue('/_fragment')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#path')
                        ->end()
                    ->end()
                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#fragments')
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
                        ->booleanNode('collect')
                            ->defaultTrue()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#collect')
                        ->end()
                        ->booleanNode('only_exceptions')
                            ->defaultFalse()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#only-exceptions')
                        ->end()
                        ->booleanNode('only_master_requests')
                            ->defaultFalse()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#only-master-requests')
                        ->end()
                        ->scalarNode('dsn')
                            ->defaultValue('file:%kernel.cache_dir%/profiler')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#dsn')
                        ->end()
                        ->arrayNode('matcher')
                            ->canBeEnabled()
                            ->performNoDeepMerging()
                            ->fixXmlConfig('ip')
                            ->children()
                                ->scalarNode('path')
                                    ->info('use the urldecoded format')
                                    ->example('^/path to resource/')
                                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-profiler-matcher-path')
                                ->end()
                                ->scalarNode('service')
                                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#service')
                                ->end()
                                ->arrayNode('ips')
                                    ->beforeNormalization()->ifString()->then(function ($v) { return array($v); })->end()
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#matcher')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addWorkflowSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('workflow')
            ->children()
                ->arrayNode('workflows')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->fixXmlConfig('support')
                        ->fixXmlConfig('place')
                        ->fixXmlConfig('transition')
                        ->children()
                            ->enumNode('type')
                                ->values(array('workflow', 'state_machine'))
                                ->defaultValue('workflow')
                            ->end()
                            ->arrayNode('marking_store')
                                ->fixXmlConfig('argument')
                                ->children()
                                    ->enumNode('type')
                                        ->values(array('multiple_state', 'single_state'))
                                    ->end()
                                    ->arrayNode('arguments')
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(function ($v) { return array($v); })
                                        ->end()
                                        ->requiresAtLeastOneElement()
                                        ->prototype('scalar')
                                        ->end()
                                    ->end()
                                    ->scalarNode('service')
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($v) { return isset($v['type']) && isset($v['service']); })
                                    ->thenInvalid('"type" and "service" cannot be used together.')
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($v) { return !empty($v['arguments']) && isset($v['service']); })
                                    ->thenInvalid('"arguments" and "service" cannot be used together.')
                                ->end()
                            ->end()
                            ->arrayNode('supports')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function ($v) { return array($v); })
                                ->end()
                                ->prototype('scalar')
                                    ->cannotBeEmpty()
                                    ->validate()
                                        ->ifTrue(function ($v) { return !class_exists($v); })
                                        ->thenInvalid('The supported class %s does not exist.')
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('support_strategy')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('initial_place')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('places')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->prototype('scalar')
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                            ->arrayNode('transitions')
                                ->beforeNormalization()
                                    ->always()
                                    ->then(function ($transitions) {
                                        // It's an indexed array, we let the validation occurs
                                        if (isset($transitions[0])) {
                                            return $transitions;
                                        }

                                        foreach ($transitions as $name => $transition) {
                                            if (array_key_exists('name', $transition)) {
                                                continue;
                                            }
                                            $transition['name'] = $name;
                                            $transitions[$name] = $transition;
                                        }

                                        return $transitions;
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
                                        ->arrayNode('from')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($v) { return array($v); })
                                            ->end()
                                            ->requiresAtLeastOneElement()
                                            ->prototype('scalar')
                                                ->cannotBeEmpty()
                                            ->end()
                                        ->end()
                                        ->arrayNode('to')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($v) { return array($v); })
                                            ->end()
                                            ->requiresAtLeastOneElement()
                                            ->prototype('scalar')
                                                ->cannotBeEmpty()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return $v['supports'] && isset($v['support_strategy']);
                            })
                            ->thenInvalid('"supports" and "support_strategy" cannot be used together.')
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return !$v['supports'] && !isset($v['support_strategy']);
                            })
                            ->thenInvalid('"supports" or "support_strategy" should be configured.')
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
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('resource')
                            ->isRequired()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#resource')
                        ->end()
                        ->scalarNode('type')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#type')
                        ->end()
                        ->scalarNode('http_port')
                            ->defaultValue(80)
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#http-port')
                        ->end()
                        ->scalarNode('https_port')
                            ->defaultValue(443)
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#https-port')
                        ->end()
                        ->scalarNode('strict_requirements')
                            ->info(
                                "set to true to throw an exception when a parameter does not match the requirements\n".
                                "set to false to disable exceptions when a parameter does not match the requirements (and return null instead)\n".
                                "set to null to disable parameter checks against requirements\n".
                                "'true' is the preferred configuration in development mode, while 'false' or 'null' might be preferred in production"
                            )
                            ->defaultTrue()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#strict-requirements')
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
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('storage_id')
                            ->defaultValue('session.storage.native')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#storage-id')
                        ->end()
                        ->scalarNode('handler_id')
                            ->defaultValue('session.handler.native_file')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#handler-id')
                        ->end()
                        ->scalarNode('name')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#name')
                        ->end()
                        ->scalarNode('cookie_lifetime')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#cookie-lifetime')
                        ->end()
                        ->scalarNode('cookie_path')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#cookie-path')
                        ->end()
                        ->scalarNode('cookie_domain')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#cookie-domain')
                        ->end()
                        ->booleanNode('cookie_secure')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#cookie-secure')
                        ->end()
                        ->booleanNode('cookie_httponly')
                            ->defaultTrue()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#cookie-httponly')
                        ->end()
                        ->booleanNode('use_cookies')->end()
                        ->scalarNode('gc_divisor')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#gc-divisor')
                        ->end()
                        ->scalarNode('gc_probability')
                            ->defaultValue(1)
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#gc-probability')
                        ->end()
                        ->scalarNode('gc_maxlifetime')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#gc-maxlifetime')
                        ->end()
                        ->scalarNode('save_path')
                            ->defaultValue('%kernel.cache_dir%/sessions')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#save-path')
                        ->end()
                        ->integerNode('metadata_update_threshold')
                            ->defaultValue('0')
                            ->info('seconds to wait between 2 session metadata updates, it will also prevent the session handler to write if the session has not changed')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addRequestSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('request')
                    ->info('request configuration')
                    ->canBeEnabled()
                    ->fixXmlConfig('format')
                    ->children()
                        ->arrayNode('formats')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->beforeNormalization()
                                    ->ifTrue(function ($v) { return is_array($v) && isset($v['mime_type']); })
                                    ->then(function ($v) { return $v['mime_type']; })
                                ->end()
                                ->beforeNormalization()
                                    ->ifTrue(function ($v) { return !is_array($v); })
                                    ->then(function ($v) { return array($v); })
                                ->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
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
                    ->info('templating configuration')
                    ->canBeEnabled()
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return false === $v || is_array($v) && false === $v['enabled']; })
                        ->then(function () { return array('enabled' => false, 'engines' => false); })
                    ->end()
                    ->children()
                        ->scalarNode('hinclude_default_template')
                            ->defaultNull()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#hinclude-default-template')
                        ->end()
                        ->scalarNode('cache')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#cache')
                        ->end()
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
                                    ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#resources')
                                ->end()
                            ->end()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-templating-form')
                        ->end()
                    ->end()
                    ->fixXmlConfig('engine')
                    ->children()
                        ->arrayNode('engines')
                            ->example(array('twig'))
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->canBeUnset()
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return !is_array($v) && false !== $v; })
                                ->then(function ($v) { return array($v); })
                            ->end()
                            ->prototype('scalar')->end()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#engines')
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
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#loaders')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addAssetsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('assets')
                    ->info('assets configuration')
                    ->{!class_exists(FullStack::class) && class_exists(Package::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->fixXmlConfig('base_url')
                    ->children()
                        ->scalarNode('version_strategy')
                            ->defaultNull()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#version-strategy')
                        ->end()
                        ->scalarNode('version')
                            ->defaultNull()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#version')
                        ->end()
                        ->scalarNode('version_format')
                            ->defaultValue('%%s?%%s')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#version-format')
                        ->end()
                        ->scalarNode('base_path')
                            ->defaultValue('')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#base-path')
                        ->end()
                        ->arrayNode('base_urls')
                            ->requiresAtLeastOneElement()
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return !is_array($v); })
                                ->then(function ($v) { return array($v); })
                            ->end()
                            ->prototype('scalar')->end()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#base-urls')
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return isset($v['version_strategy']) && isset($v['version']);
                        })
                        ->thenInvalid('You cannot use both "version_strategy" and "version" at the same time under "assets".')
                    ->end()
                    ->fixXmlConfig('package')
                    ->children()
                        ->arrayNode('packages')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->fixXmlConfig('base_url')
                                ->children()
                                    ->scalarNode('version_strategy')
                                        ->defaultNull()
                                        ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-assets-version-strategy')
                                    ->end()
                                    ->scalarNode('version')
                                        ->beforeNormalization()
                                        ->ifTrue(function ($v) { return '' === $v; })
                                        ->then(function ($v) { return; })
                                        ->end()
                                        ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-framework-assets-version')
                                    ->end()
                                    ->scalarNode('version_format')
                                        ->defaultNull()
                                        ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-assets-version-format')
                                    ->end()
                                    ->scalarNode('base_path')
                                        ->defaultValue('')
                                        ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-assets-base-path')
                                    ->end()
                                    ->arrayNode('base_urls')
                                        ->requiresAtLeastOneElement()
                                        ->beforeNormalization()
                                            ->ifTrue(function ($v) { return !is_array($v); })
                                            ->then(function ($v) { return array($v); })
                                        ->end()
                                        ->prototype('scalar')->end()
                                        ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-assets-base-urls')
                                    ->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($v) {
                                        return isset($v['version_strategy']) && isset($v['version']);
                                    })
                                    ->thenInvalid('You cannot use both "version_strategy" and "version" at the same time under "assets" packages.')
                                ->end()
                            ->end()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#packages')
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
                    ->{!class_exists(FullStack::class) && class_exists(Translator::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->fixXmlConfig('fallback')
                    ->fixXmlConfig('path')
                    ->children()
                        ->arrayNode('fallbacks')
                            ->beforeNormalization()->ifString()->then(function ($v) { return array($v); })->end()
                            ->prototype('scalar')->end()
                            ->defaultValue(array('en'))
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#fallbacks')
                        ->end()
                        ->booleanNode('logging')
                            ->defaultValue($this->debug)
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#logging')
                        ->end()
                        ->arrayNode('paths')
                            ->prototype('scalar')->end()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#paths')
                        ->end()
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
                    ->{!class_exists(FullStack::class) && class_exists(Validation::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->children()
                        ->scalarNode('cache')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-validation-cache')
                        ->end()
                        ->booleanNode('enable_annotations')
                            ->{!class_exists(FullStack::class) && class_exists(Annotation::class) ? 'defaultTrue' : 'defaultFalse'}()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#enable-annotations')
                        ->end()
                        ->arrayNode('static_method')
                            ->defaultValue(array('loadValidatorMetadata'))
                            ->prototype('scalar')->end()
                            ->treatFalseLike(array())
                            ->validate()
                                ->ifTrue(function ($v) { return !is_array($v); })
                                ->then(function ($v) { return (array) $v; })
                            ->end()
                        ->end()
                        ->scalarNode('translation_domain')
                            ->defaultValue('validators')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#translation-domain')
                        ->end()
                        ->booleanNode('strict_email')
                            ->defaultFalse()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#strict-email')
                        ->end()
                        ->arrayNode('mapping')
                            ->addDefaultsIfNotSet()
                            ->fixXmlConfig('path')
                            ->children()
                                ->arrayNode('paths')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#mapping')
                        ->end()
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
                    ->{class_exists(Annotation::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->children()
                        ->scalarNode('cache')
                            ->defaultValue('php_array')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-annotations-cache')
                        ->end()
                        ->scalarNode('file_cache_dir')
                            ->defaultValue('%kernel.cache_dir%/annotations')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#file-cache-dir')
                        ->end()
                        ->booleanNode('debug')
                            ->defaultValue($this->debug)
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#debug')
                        ->end()
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
                    ->{!class_exists(FullStack::class) && class_exists(Serializer::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->children()
                        ->booleanNode('enable_annotations')->{!class_exists(FullStack::class) && class_exists(Annotation::class) ? 'defaultTrue' : 'defaultFalse'}()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-serializer-enable-annotations')
                        ->end()
                        ->scalarNode('cache')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#reference-serializer-cache')
                        ->end()
                        ->scalarNode('name_converter')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#name-converter')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addPropertyAccessSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('property_access')
                    ->addDefaultsIfNotSet()
                    ->info('Property access configuration')
                    ->children()
                        ->booleanNode('magic_call')
                            ->defaultFalse()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#magic-call')
                        ->end()
                        ->booleanNode('throw_exception_on_invalid_index')
                            ->defaultFalse()
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#throw-exception-on-invalid-index')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addPropertyInfoSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('property_info')
                    ->info('Property info configuration')
                    ->canBeEnabled()
                ->end()
            ->end()
        ;
    }

    private function addCacheSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('cache')
                    ->info('Cache configuration')
                    ->addDefaultsIfNotSet()
                    ->fixXmlConfig('pool')
                    ->children()
                        ->scalarNode('prefix_seed')
                            ->info('Used to namespace cache keys when using several apps with the same shared backend')
                            ->example('my-application-name')
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#prefix-seed')
                        ->end()
                        ->scalarNode('app')
                            ->info('App related cache pools configuration')
                            ->defaultValue('cache.adapter.filesystem')
                        ->end()
                        ->scalarNode('system')
                            ->info('System related cache pools configuration')
                            ->defaultValue('cache.adapter.system')
                        ->end()
                        ->scalarNode('directory')->defaultValue('%kernel.cache_dir%/pools')->end()
                        ->scalarNode('default_doctrine_provider')->end()
                        ->scalarNode('default_psr6_provider')->end()
                        ->scalarNode('default_redis_provider')->defaultValue('redis://localhost')->end()
                        ->scalarNode('default_memcached_provider')->defaultValue('memcached://localhost')->end()
                        ->arrayNode('pools')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('adapter')->defaultValue('cache.app')->end()
                                    ->booleanNode('public')->defaultFalse()->end()
                                    ->integerNode('default_lifetime')->end()
                                    ->scalarNode('provider')
                                        ->info('The service name to use as provider when the specified adapter needs one.')
                                    ->end()
                                    ->scalarNode('clearer')->end()
                                ->end()
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) { return isset($v['cache.app']) || isset($v['cache.system']); })
                                ->thenInvalid('"cache.app" and "cache.system" are reserved names')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addPhpErrorsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('php_errors')
                    ->info('PHP errors handling configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('log')
                            ->info('Use the app logger instead of the PHP logger for logging PHP errors.')
                            ->defaultValue($this->debug)
                            ->treatNullLike($this->debug)
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#log')
                        ->end()
                        ->booleanNode('throw')
                            ->info('Throw PHP errors as \ErrorException instances.')
                            ->defaultValue($this->debug)
                            ->treatNullLike($this->debug)
                            ->doc('https://symfony.com/doc/%s/reference/configuration/framework.html#throw')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
