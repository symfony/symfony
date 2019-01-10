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
use Doctrine\Common\Cache\Cache;
use Symfony\Bundle\FullStack;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Form\Form;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\WebLink\HttpHeaderSerializer;

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
                ->ifTrue(function ($v) { return !isset($v['assets']) && isset($v['templating']) && class_exists(Package::class); })
                ->then(function ($v) {
                    $v['assets'] = [];

                    return $v;
                })
            ->end()
            ->children()
                ->scalarNode('secret')->end()
                ->scalarNode('http_method_override')
                    ->info("Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests. Note: When using the HttpCache, you need to call the method in your front controller instead")
                    ->defaultTrue()
                ->end()
                ->arrayNode('trusted_proxies')
                    ->setDeprecated('The "%path%.%node%" configuration key has been deprecated in Symfony 3.3. Use the Request::setTrustedProxies() method in your front controller instead.')
                    ->beforeNormalization()
                        ->ifTrue(function ($v) {
                            return !\is_array($v) && null !== $v;
                        })
                        ->then(function ($v) { return \is_bool($v) ? [] : preg_split('/\s*,\s*/', $v); })
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
                ->end()
                ->scalarNode('ide')->defaultNull()->end()
                ->booleanNode('test')->end()
                ->scalarNode('default_locale')->defaultValue('en')->end()
                ->arrayNode('trusted_hosts')
                    ->beforeNormalization()->ifString()->then(function ($v) { return [$v]; })->end()
                    ->prototype('scalar')->end()
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
        $this->addWebLinkSection($rootNode);
        $this->addLockSection($rootNode);

        return $treeBuilder;
    }

    private function addCsrfSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('csrf_protection')
                    ->treatFalseLike(['enabled' => false])
                    ->treatTrueLike(['enabled' => true])
                    ->treatNullLike(['enabled' => true])
                    ->addDefaultsIfNotSet()
                    ->children()
                        // defaults to framework.session.enabled && !class_exists(FullStack::class) && interface_exists(CsrfTokenManagerInterface::class)
                        ->booleanNode('enabled')->defaultNull()->end()
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
                    ->info('form configuration')
                    ->{!class_exists(FullStack::class) && class_exists(Form::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->children()
                        ->arrayNode('csrf_protection')
                            ->treatFalseLike(['enabled' => false])
                            ->treatTrueLike(['enabled' => true])
                            ->treatNullLike(['enabled' => true])
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultNull()->end() // defaults to framework.csrf_protection.enabled
                                ->scalarNode('field_name')->defaultValue('_token')->end()
                            ->end()
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
                        ->arrayNode('matcher')
                            ->setDeprecated('The "profiler.matcher" configuration key has been deprecated in Symfony 3.4 and it will be removed in 4.0.')
                            ->canBeEnabled()
                            ->performNoDeepMerging()
                            ->fixXmlConfig('ip')
                            ->children()
                                ->scalarNode('path')
                                    ->info('use the urldecoded format')
                                    ->example('^/path to resource/')
                                ->end()
                                ->scalarNode('service')->end()
                                ->arrayNode('ips')
                                    ->beforeNormalization()->ifString()->then(function ($v) { return [$v]; })->end()
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
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
                    ->canBeEnabled()
                    ->beforeNormalization()
                        ->always(function ($v) {
                            if (true === $v['enabled']) {
                                $workflows = $v;
                                unset($workflows['enabled']);

                                if (1 === \count($workflows) && isset($workflows[0]['enabled']) && 1 === \count($workflows[0])) {
                                    $workflows = [];
                                }

                                if (1 === \count($workflows) && isset($workflows['workflows']) && array_keys($workflows['workflows']) !== range(0, \count($workflows) - 1) && !empty(array_diff(array_keys($workflows['workflows']), ['audit_trail', 'type', 'marking_store', 'supports', 'support_strategy', 'initial_place', 'places', 'transitions']))) {
                                    $workflows = $workflows['workflows'];
                                }

                                foreach ($workflows as $key => $workflow) {
                                    if (isset($workflow['enabled']) && false === $workflow['enabled']) {
                                        throw new LogicException(sprintf('Cannot disable a single workflow. Remove the configuration for the workflow "%s" instead.', $workflow['name']));
                                    }

                                    unset($workflows[$key]['enabled']);
                                }

                                $v = [
                                    'enabled' => true,
                                    'workflows' => $workflows,
                                ];
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->arrayNode('workflows')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->fixXmlConfig('support')
                                ->fixXmlConfig('place')
                                ->fixXmlConfig('transition')
                                ->children()
                                    ->arrayNode('audit_trail')
                                        ->canBeEnabled()
                                    ->end()
                                    ->enumNode('type')
                                        ->values(['workflow', 'state_machine'])
                                    ->end()
                                    ->arrayNode('marking_store')
                                        ->fixXmlConfig('argument')
                                        ->children()
                                            ->enumNode('type')
                                                ->values(['multiple_state', 'single_state'])
                                            ->end()
                                            ->arrayNode('arguments')
                                                ->beforeNormalization()
                                                    ->ifString()
                                                    ->then(function ($v) { return [$v]; })
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
                                            ->then(function ($v) { return [$v]; })
                                        ->end()
                                        ->prototype('scalar')
                                            ->cannotBeEmpty()
                                            ->validate()
                                                ->ifTrue(function ($v) { return !class_exists($v) && !interface_exists($v); })
                                                ->thenInvalid('The supported class or interface "%s" does not exist.')
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
                                                    if (\array_key_exists('name', $transition)) {
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
                                                ->scalarNode('guard')
                                                    ->cannotBeEmpty()
                                                    ->info('An expression to block the transition')
                                                    ->example('is_fully_authenticated() and has_role(\'ROLE_JOURNALIST\') and subject.getTitle() == \'My first article\'')
                                                ->end()
                                                ->arrayNode('from')
                                                    ->beforeNormalization()
                                                        ->ifString()
                                                        ->then(function ($v) { return [$v]; })
                                                    ->end()
                                                    ->requiresAtLeastOneElement()
                                                    ->prototype('scalar')
                                                        ->cannotBeEmpty()
                                                    ->end()
                                                ->end()
                                                ->arrayNode('to')
                                                    ->beforeNormalization()
                                                        ->ifString()
                                                        ->then(function ($v) { return [$v]; })
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
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('storage_id')->defaultValue('session.storage.native')->end()
                        ->scalarNode('handler_id')->defaultValue('session.handler.native_file')->end()
                        ->scalarNode('name')
                            ->validate()
                                ->ifTrue(function ($v) {
                                    parse_str($v, $parsed);

                                    return implode('&', array_keys($parsed)) !== (string) $v;
                                })
                                ->thenInvalid('Session name %s contains illegal character(s)')
                            ->end()
                        ->end()
                        ->scalarNode('cookie_lifetime')->end()
                        ->scalarNode('cookie_path')->end()
                        ->scalarNode('cookie_domain')->end()
                        ->booleanNode('cookie_secure')->end()
                        ->booleanNode('cookie_httponly')->defaultTrue()->end()
                        ->booleanNode('use_cookies')->end()
                        ->scalarNode('gc_divisor')->end()
                        ->scalarNode('gc_probability')->defaultValue(1)->end()
                        ->scalarNode('gc_maxlifetime')->end()
                        ->booleanNode('use_strict_mode')
                            ->defaultTrue()
                            ->setDeprecated('The "%path%.%node%" option is enabled by default and deprecated since Symfony 3.4. It will be always enabled in 4.0.')
                        ->end()
                        ->scalarNode('save_path')->defaultValue('%kernel.cache_dir%/sessions')->end()
                        ->integerNode('metadata_update_threshold')
                            ->defaultValue('0')
                            ->info('seconds to wait between 2 session metadata updates')
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
                                    ->ifTrue(function ($v) { return \is_array($v) && isset($v['mime_type']); })
                                    ->then(function ($v) { return $v['mime_type']; })
                                ->end()
                                ->beforeNormalization()
                                    ->ifTrue(function ($v) { return !\is_array($v); })
                                    ->then(function ($v) { return [$v]; })
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
                        ->ifTrue(function ($v) { return false === $v || \is_array($v) && false === $v['enabled']; })
                        ->then(function () { return ['enabled' => false, 'engines' => false]; })
                    ->end()
                    ->children()
                        ->scalarNode('hinclude_default_template')->defaultNull()->end()
                        ->scalarNode('cache')->end()
                        ->arrayNode('form')
                            ->addDefaultsIfNotSet()
                            ->fixXmlConfig('resource')
                            ->children()
                                ->arrayNode('resources')
                                    ->addDefaultChildrenIfNoneSet()
                                    ->prototype('scalar')->defaultValue('FrameworkBundle:Form')->end()
                                    ->validate()
                                        ->ifTrue(function ($v) {return !\in_array('FrameworkBundle:Form', $v); })
                                        ->then(function ($v) {
                                            return array_merge(['FrameworkBundle:Form'], $v);
                                        })
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->fixXmlConfig('engine')
                    ->children()
                        ->arrayNode('engines')
                            ->example(['twig'])
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->canBeUnset()
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return !\is_array($v) && false !== $v; })
                                ->then(function ($v) { return [$v]; })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                    ->fixXmlConfig('loader')
                    ->children()
                        ->arrayNode('loaders')
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return !\is_array($v); })
                                ->then(function ($v) { return [$v]; })
                             ->end()
                            ->prototype('scalar')->end()
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
                        ->scalarNode('version_strategy')->defaultNull()->end()
                        ->scalarNode('version')->defaultNull()->end()
                        ->scalarNode('version_format')->defaultValue('%%s?%%s')->end()
                        ->scalarNode('json_manifest_path')->defaultNull()->end()
                        ->scalarNode('base_path')->defaultValue('')->end()
                        ->arrayNode('base_urls')
                            ->requiresAtLeastOneElement()
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return !\is_array($v); })
                                ->then(function ($v) { return [$v]; })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return isset($v['version_strategy']) && isset($v['version']);
                        })
                        ->thenInvalid('You cannot use both "version_strategy" and "version" at the same time under "assets".')
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return isset($v['version_strategy']) && isset($v['json_manifest_path']);
                        })
                        ->thenInvalid('You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets".')
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return isset($v['version']) && isset($v['json_manifest_path']);
                        })
                        ->thenInvalid('You cannot use both "version" and "json_manifest_path" at the same time under "assets".')
                    ->end()
                    ->fixXmlConfig('package')
                    ->children()
                        ->arrayNode('packages')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->fixXmlConfig('base_url')
                                ->children()
                                    ->scalarNode('version_strategy')->defaultNull()->end()
                                    ->scalarNode('version')
                                        ->beforeNormalization()
                                        ->ifTrue(function ($v) { return '' === $v; })
                                        ->then(function ($v) { return; })
                                        ->end()
                                    ->end()
                                    ->scalarNode('version_format')->defaultNull()->end()
                                    ->scalarNode('json_manifest_path')->defaultNull()->end()
                                    ->scalarNode('base_path')->defaultValue('')->end()
                                    ->arrayNode('base_urls')
                                        ->requiresAtLeastOneElement()
                                        ->beforeNormalization()
                                            ->ifTrue(function ($v) { return !\is_array($v); })
                                            ->then(function ($v) { return [$v]; })
                                        ->end()
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($v) {
                                        return isset($v['version_strategy']) && isset($v['version']);
                                    })
                                    ->thenInvalid('You cannot use both "version_strategy" and "version" at the same time under "assets" packages.')
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($v) {
                                        return isset($v['version_strategy']) && isset($v['json_manifest_path']);
                                    })
                                    ->thenInvalid('You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets" packages.')
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($v) {
                                        return isset($v['version']) && isset($v['json_manifest_path']);
                                    })
                                    ->thenInvalid('You cannot use both "version" and "json_manifest_path" at the same time under "assets" packages.')
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
                    ->{!class_exists(FullStack::class) && class_exists(Translator::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->fixXmlConfig('fallback')
                    ->fixXmlConfig('path')
                    ->children()
                        ->arrayNode('fallbacks')
                            ->beforeNormalization()->ifString()->then(function ($v) { return [$v]; })->end()
                            ->prototype('scalar')->end()
                            ->defaultValue(['en'])
                        ->end()
                        ->booleanNode('logging')->defaultValue($this->debug)->end()
                        ->scalarNode('formatter')->defaultValue('translator.formatter.default')->end()
                        ->scalarNode('default_path')
                            ->info('The default path used to load translations')
                            ->defaultValue('%kernel.project_dir%/translations')
                        ->end()
                        ->arrayNode('paths')
                            ->prototype('scalar')->end()
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
                            // Can be removed in 4.0, when validator.mapping.cache.doctrine.apc is removed
                            ->setDeprecated('The "%path%.%node%" option is deprecated since Symfony 3.2 and will be removed in 4.0. Configure the "cache.validator" service under "framework.cache.pools" instead.')
                            ->beforeNormalization()
                                ->ifString()->then(function ($v) {
                                    if ('validator.mapping.cache.doctrine.apc' === $v && !class_exists('Doctrine\Common\Cache\ApcCache')) {
                                        throw new LogicException('Doctrine APC cache for the validator cannot be enabled as the Doctrine Cache package is not installed.');
                                    }

                                    return $v;
                                })
                            ->end()
                        ->end()
                        ->booleanNode('enable_annotations')->{!class_exists(FullStack::class) && class_exists(Annotation::class) ? 'defaultTrue' : 'defaultFalse'}()->end()
                        ->arrayNode('static_method')
                            ->defaultValue(['loadValidatorMetadata'])
                            ->prototype('scalar')->end()
                            ->treatFalseLike([])
                            ->validate()
                                ->ifTrue(function ($v) { return !\is_array($v); })
                                ->then(function ($v) { return (array) $v; })
                            ->end()
                        ->end()
                        ->scalarNode('translation_domain')->defaultValue('validators')->end()
                        ->booleanNode('strict_email')->defaultFalse()->end()
                        ->arrayNode('mapping')
                            ->addDefaultsIfNotSet()
                            ->fixXmlConfig('path')
                            ->children()
                                ->arrayNode('paths')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
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
                        ->scalarNode('cache')->defaultValue(interface_exists(Cache::class) ? 'php_array' : 'none')->end()
                        ->scalarNode('file_cache_dir')->defaultValue('%kernel.cache_dir%/annotations')->end()
                        ->booleanNode('debug')->defaultValue($this->debug)->end()
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
                        ->booleanNode('enable_annotations')->{!class_exists(FullStack::class) && class_exists(Annotation::class) ? 'defaultTrue' : 'defaultFalse'}()->end()
                        ->scalarNode('cache')
                            ->setDeprecated('The "%path%.%node%" option is deprecated since Symfony 3.1 and will be removed in 4.0. Configure the "cache.serializer" service under "framework.cache.pools" instead.')
                        ->end()
                        ->scalarNode('name_converter')->end()
                        ->scalarNode('circular_reference_handler')->end()
                        ->arrayNode('mapping')
                            ->addDefaultsIfNotSet()
                            ->fixXmlConfig('path')
                            ->children()
                                ->arrayNode('paths')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
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
                        ->booleanNode('magic_call')->defaultFalse()->end()
                        ->booleanNode('throw_exception_on_invalid_index')->defaultFalse()->end()
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
                    ->{!class_exists(FullStack::class) && interface_exists(PropertyInfoExtractorInterface::class) ? 'canBeDisabled' : 'canBeEnabled'}()
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
                        ->end()
                        ->booleanNode('throw')
                            ->info('Throw PHP errors as \ErrorException instances.')
                            ->defaultValue($this->debug)
                            ->treatNullLike($this->debug)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addLockSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('lock')
                    ->info('Lock configuration')
                    ->{!class_exists(FullStack::class) && class_exists(Lock::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->beforeNormalization()
                        ->ifString()->then(function ($v) { return ['enabled' => true, 'resources' => $v]; })
                    ->end()
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return \is_array($v) && !isset($v['resources']); })
                        ->then(function ($v) {
                            $e = $v['enabled'];
                            unset($v['enabled']);

                            return ['enabled' => $e, 'resources' => $v];
                        })
                    ->end()
                    ->addDefaultsIfNotSet()
                    ->fixXmlConfig('resource')
                    ->children()
                        ->arrayNode('resources')
                            ->requiresAtLeastOneElement()
                            ->defaultValue(['default' => [class_exists(SemaphoreStore::class) && SemaphoreStore::isSupported() ? 'semaphore' : 'flock']])
                            ->beforeNormalization()
                                ->ifString()->then(function ($v) { return ['default' => $v]; })
                            ->end()
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return \is_array($v) && array_keys($v) === range(0, \count($v) - 1); })
                                ->then(function ($v) { return ['default' => $v]; })
                            ->end()
                            ->prototype('array')
                                ->beforeNormalization()->ifString()->then(function ($v) { return [$v]; })->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addWebLinkSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('web_link')
                    ->info('web links configuration')
                    ->{!class_exists(FullStack::class) && class_exists(HttpHeaderSerializer::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                ->end()
            ->end()
        ;
    }
}
