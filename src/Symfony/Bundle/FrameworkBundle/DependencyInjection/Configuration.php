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
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FullStack;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\WebLink\HttpHeaderSerializer;

/**
 * FrameworkExtension configuration structure.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Configuration implements ConfigurationInterface
{
    private $debug;

    /**
     * @param bool $debug Whether debugging is enabled or not
     */
    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('framework');
        $rootNode = $treeBuilder->getRootNode();

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
                ->scalarNode('ide')->defaultNull()->end()
                ->booleanNode('test')->end()
                ->scalarNode('default_locale')->defaultValue('en')->end()
                ->arrayNode('trusted_hosts')
                    ->beforeNormalization()->ifString()->then(function ($v) { return [$v]; })->end()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('error_controller')
                    ->defaultValue('error_controller')
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
        $this->addMessengerSection($rootNode);
        $this->addRobotsIndexSection($rootNode);
        $this->addHttpClientSection($rootNode);
        $this->addMailerSection($rootNode);
        $this->addSecretsSection($rootNode);

        return $treeBuilder;
    }

    private function addSecretsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('secrets')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('vault_directory')->defaultValue('%kernel.project_dir%/config/secrets/%kernel.environment%')->cannotBeEmpty()->end()
                        ->scalarNode('local_dotenv_file')->defaultValue('%kernel.project_dir%/.env.%kernel.environment%.local')->end()
                        ->scalarNode('decryption_env_var')->defaultValue('base64:default::SYMFONY_DECRYPTION_SECRET')->end()
                    ->end()
                ->end()
            ->end()
        ;
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
                        ->scalarNode('hinclude_default_template')->defaultNull()->end()
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
                            if (\is_array($v) && true === $v['enabled']) {
                                $workflows = $v;
                                unset($workflows['enabled']);

                                if (1 === \count($workflows) && isset($workflows[0]['enabled']) && 1 === \count($workflows[0])) {
                                    $workflows = [];
                                }

                                if (1 === \count($workflows) && isset($workflows['workflows']) && array_keys($workflows['workflows']) !== range(0, \count($workflows) - 1) && !empty(array_diff(array_keys($workflows['workflows']), ['audit_trail', 'type', 'marking_store', 'supports', 'support_strategy', 'initial_marking', 'places', 'transitions']))) {
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
                                ->beforeNormalization()
                                    ->always(function ($v) {
                                        if (isset($v['initial_place'])) {
                                            $v['initial_marking'] = [$v['initial_place']];
                                        }

                                        return $v;
                                    })
                                ->end()
                                ->fixXmlConfig('support')
                                ->fixXmlConfig('place')
                                ->fixXmlConfig('transition')
                                ->children()
                                    ->arrayNode('audit_trail')
                                        ->canBeEnabled()
                                    ->end()
                                    ->enumNode('type')
                                        ->values(['workflow', 'state_machine'])
                                        ->defaultValue('state_machine')
                                    ->end()
                                    ->arrayNode('marking_store')
                                        ->fixXmlConfig('argument')
                                        ->children()
                                            ->enumNode('type')
                                                ->values(['multiple_state', 'single_state', 'method'])
                                                ->validate()
                                                    ->ifTrue(function ($v) { return 'method' !== $v; })
                                                    ->then(function ($v) {
                                                        @trigger_error('Passing something else than "method" has been deprecated in Symfony 4.3.', \E_USER_DEPRECATED);

                                                        return $v;
                                                    })
                                                ->end()
                                            ->end()
                                            ->arrayNode('arguments')
                                                ->setDeprecated('The "%path%.%node%" configuration key has been deprecated in Symfony 4.3. Use "property" instead.')
                                                ->beforeNormalization()
                                                    ->ifString()
                                                    ->then(function ($v) { return [$v]; })
                                                ->end()
                                                ->requiresAtLeastOneElement()
                                                ->prototype('scalar')
                                                ->end()
                                            ->end()
                                            ->scalarNode('property')
                                                ->defaultNull() // In Symfony 5.0, set "marking" as default property
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
                                                ->ifTrue(function ($v) { return !class_exists($v) && !interface_exists($v, false); })
                                                ->thenInvalid('The supported class or interface "%s" does not exist.')
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->scalarNode('support_strategy')
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('initial_place')
                                        ->setDeprecated('The "%path%.%node%" configuration key has been deprecated in Symfony 4.3, use the "initial_marking" configuration key instead.')
                                        ->defaultNull()
                                    ->end()
                                    ->arrayNode('initial_marking')
                                        ->beforeNormalization()->castToArray()->end()
                                        ->defaultValue([])
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->arrayNode('places')
                                        ->beforeNormalization()
                                            ->always()
                                            ->then(function ($places) {
                                                // It's an indexed array of shape  ['place1', 'place2']
                                                if (isset($places[0]) && \is_string($places[0])) {
                                                    return array_map(function (string $place) {
                                                        return ['name' => $place];
                                                    }, $places);
                                                }

                                                // It's an indexed array, we let the validation occur
                                                if (isset($places[0]) && \is_array($places[0])) {
                                                    return $places;
                                                }

                                                foreach ($places as $name => $place) {
                                                    if (\is_array($place) && \array_key_exists('name', $place)) {
                                                        continue;
                                                    }
                                                    $place['name'] = $name;
                                                    $places[$name] = $place;
                                                }

                                                return array_values($places);
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
                                                ->arrayNode('metadata')
                                                    ->normalizeKeys(false)
                                                    ->defaultValue([])
                                                    ->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])
                                                    ->prototype('variable')
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('transitions')
                                        ->beforeNormalization()
                                            ->always()
                                            ->then(function ($transitions) {
                                                // It's an indexed array, we let the validation occur
                                                if (isset($transitions[0]) && \is_array($transitions[0])) {
                                                    return $transitions;
                                                }

                                                foreach ($transitions as $name => $transition) {
                                                    if (\is_array($transition) && \array_key_exists('name', $transition)) {
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
                                                    ->example('is_fully_authenticated() and is_granted(\'ROLE_JOURNALIST\') and subject.getTitle() == \'My first article\'')
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
                                                ->arrayNode('metadata')
                                                    ->normalizeKeys(false)
                                                    ->defaultValue([])
                                                    ->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])
                                                    ->prototype('variable')
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('metadata')
                                        ->normalizeKeys(false)
                                        ->defaultValue([])
                                        ->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])
                                        ->prototype('variable')
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
                                ->validate()
                                    ->ifTrue(function ($v) {
                                        return 'workflow' === $v['type'] && 'single_state' === ($v['marking_store']['type'] ?? false);
                                    })
                                    ->then(function ($v) {
                                        @trigger_error('Using a workflow with type=workflow and a marking_store=single_state is deprecated since Symfony 4.3. Use type=state_machine instead.', \E_USER_DEPRECATED);

                                        return $v;
                                    })
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($v) {
                                        return isset($v['marking_store']['property'])
                                            && (!isset($v['marking_store']['type']) || 'method' !== $v['marking_store']['type'])
                                        ;
                                    })
                                    ->thenInvalid('"property" option is only supported by the "method" marking store.')
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
                        ->booleanNode('utf8')->defaultFalse()->end()
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
                        ->enumNode('cookie_secure')->values([true, false, 'auto'])->end()
                        ->booleanNode('cookie_httponly')->defaultTrue()->end()
                        ->enumNode('cookie_samesite')->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE])->defaultNull()->end()
                        ->booleanNode('use_cookies')->end()
                        ->scalarNode('gc_divisor')->end()
                        ->scalarNode('gc_probability')->defaultValue(1)->end()
                        ->scalarNode('gc_maxlifetime')->end()
                        ->scalarNode('save_path')->defaultValue('%kernel.cache_dir%/sessions')->end()
                        ->integerNode('metadata_update_threshold')
                            ->defaultValue(0)
                            ->info('seconds to wait between 2 session metadata updates')
                        ->end()
                        ->integerNode('sid_length')
                            ->min(22)
                            ->max(256)
                        ->end()
                        ->integerNode('sid_bits_per_character')
                            ->min(4)
                            ->max(6)
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
                                ->beforeNormalization()->castToArray()->end()
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
                    ->setDeprecated('The "%path%.%node%" configuration is deprecated since Symfony 4.3. Configure the "twig" section provided by the Twig Bundle instead.')
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return false === $v || \is_array($v) && false === $v['enabled']; })
                        ->then(function () { return ['enabled' => false, 'engines' => false]; })
                    ->end()
                    ->children()
                        ->scalarNode('hinclude_default_template')->setDeprecated('Setting "templating.hinclude_default_template" is deprecated since Symfony 4.3, use "fragments.hinclude_default_template" instead.')->defaultNull()->end()
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
                            ->beforeNormalization()->castToArray()->end()
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
                            ->beforeNormalization()->castToArray()->end()
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
                                        ->beforeNormalization()->castToArray()->end()
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
                            ->info('Defaults to the value of "default_locale".')
                            ->beforeNormalization()->ifString()->then(function ($v) { return [$v]; })->end()
                            ->prototype('scalar')->end()
                            ->defaultValue([])
                        ->end()
                        ->booleanNode('logging')->defaultValue(false)->end()
                        ->scalarNode('formatter')->defaultValue('translator.formatter.default')->end()
                        ->scalarNode('cache_dir')->defaultValue('%kernel.cache_dir%/translations')->end()
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
                    ->validate()
                        ->ifTrue(function ($v) { return isset($v['strict_email']) && isset($v['email_validation_mode']); })
                        ->thenInvalid('"strict_email" and "email_validation_mode" cannot be used together.')
                    ->end()
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return isset($v['strict_email']); })
                        ->then(function ($v) {
                            @trigger_error('The "framework.validation.strict_email" configuration key has been deprecated in Symfony 4.1. Use the "framework.validation.email_validation_mode" configuration key instead.', \E_USER_DEPRECATED);

                            return $v;
                        })
                    ->end()
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return isset($v['strict_email']) && !isset($v['email_validation_mode']); })
                        ->then(function ($v) {
                            $v['email_validation_mode'] = $v['strict_email'] ? 'strict' : 'loose';
                            unset($v['strict_email']);

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('cache')->end()
                        ->booleanNode('enable_annotations')->{!class_exists(FullStack::class) && class_exists(Annotation::class) ? 'defaultTrue' : 'defaultFalse'}()->end()
                        ->arrayNode('static_method')
                            ->defaultValue(['loadValidatorMetadata'])
                            ->prototype('scalar')->end()
                            ->treatFalseLike([])
                            ->validate()->castToArray()->end()
                        ->end()
                        ->scalarNode('translation_domain')->defaultValue('validators')->end()
                        ->booleanNode('strict_email')->end()
                        ->enumNode('email_validation_mode')->values(['html5', 'loose', 'strict'])->end()
                        ->arrayNode('mapping')
                            ->addDefaultsIfNotSet()
                            ->fixXmlConfig('path')
                            ->children()
                                ->arrayNode('paths')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('not_compromised_password')
                            ->canBeDisabled()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultTrue()
                                    ->info('When disabled, compromised passwords will be accepted as valid.')
                                ->end()
                                ->scalarNode('endpoint')
                                    ->defaultNull()
                                    ->info('API endpoint for the NotCompromisedPassword Validator.')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('auto_mapping')
                            ->info('A collection of namespaces for which auto-mapping will be enabled by default, or null to opt-in with the EnableAutoMapping constraint.')
                            ->example([
                                'App\\Entity\\' => [],
                                'App\\WithSpecificLoaders\\' => ['validator.property_info_loader'],
                            ])
                            ->useAttributeAsKey('namespace')
                            ->normalizeKeys(false)
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(function (array $values): array {
                                    foreach ($values as $k => $v) {
                                        if (isset($v['service'])) {
                                            continue;
                                        }

                                        if (isset($v['namespace'])) {
                                            $values[$k]['services'] = [];
                                            continue;
                                        }

                                        if (!\is_array($v)) {
                                            $values[$v]['services'] = [];
                                            unset($values[$k]);
                                            continue;
                                        }

                                        $tmp = $v;
                                        unset($values[$k]);
                                        $values[$k]['services'] = $tmp;
                                    }

                                    return $values;
                                })
                            ->end()
                            ->arrayPrototype()
                                ->fixXmlConfig('service')
                                ->children()
                                    ->arrayNode('services')
                                        ->prototype('scalar')->end()
                                    ->end()
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
                        ->scalarNode('name_converter')->end()
                        ->scalarNode('circular_reference_handler')->end()
                        ->scalarNode('max_depth_handler')->end()
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
                        ->booleanNode('throw_exception_on_invalid_property_path')->defaultTrue()->end()
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
                        ->scalarNode('default_pdo_provider')->defaultValue(class_exists(Connection::class) ? 'database_connection' : null)->end()
                        ->arrayNode('pools')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->fixXmlConfig('adapter')
                                ->beforeNormalization()
                                    ->ifTrue(function ($v) { return (isset($v['adapters']) || \is_array($v['adapter'] ?? null)) && isset($v['provider']); })
                                    ->thenInvalid('Pool cannot have a "provider" while "adapter" is set to a map')
                                ->end()
                                ->children()
                                    ->arrayNode('adapters')
                                        ->performNoDeepMerging()
                                        ->info('One or more adapters to chain for creating the pool, defaults to "cache.app".')
                                        ->beforeNormalization()
                                            ->always()->then(function ($values) {
                                                if ([0] === array_keys($values) && \is_array($values[0])) {
                                                    return $values[0];
                                                }
                                                $adapters = [];

                                                foreach ($values as $k => $v) {
                                                    if (\is_int($k) && \is_string($v)) {
                                                        $adapters[] = $v;
                                                    } elseif (!\is_array($v)) {
                                                        $adapters[$k] = $v;
                                                    } elseif (isset($v['provider'])) {
                                                        $adapters[$v['provider']] = $v['name'] ?? $v;
                                                    } else {
                                                        $adapters[] = $v['name'] ?? $v;
                                                    }
                                                }

                                                return $adapters;
                                            })
                                        ->end()
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->scalarNode('tags')->defaultNull()->end()
                                    ->booleanNode('public')->defaultFalse()->end()
                                    ->integerNode('default_lifetime')->end()
                                    ->scalarNode('provider')
                                        ->info('Overwrite the setting from the default provider for this adapter.')
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
                        ->scalarNode('log')
                            ->info('Use the application logger instead of the PHP logger for logging PHP errors.')
                            ->example('"true" to use the default configuration: log all errors. "false" to disable. An integer bit field of E_* constants.')
                            ->defaultValue($this->debug)
                            ->treatNullLike($this->debug)
                            ->validate()
                                ->ifTrue(function ($v) { return !(\is_int($v) || \is_bool($v)); })
                                ->thenInvalid('The "php_errors.log" parameter should be either an integer or a boolean.')
                            ->end()
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
                        ->ifTrue(function ($v) { return \is_array($v) && !isset($v['enabled']); })
                        ->then(function ($v) { return $v + ['enabled' => true]; })
                    ->end()
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return \is_array($v) && !isset($v['resources']) && !isset($v['resource']); })
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
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->requiresAtLeastOneElement()
                            ->defaultValue(['default' => [class_exists(SemaphoreStore::class) && SemaphoreStore::isSupported() ? 'semaphore' : 'flock']])
                            ->beforeNormalization()
                                ->ifString()->then(function ($v) { return ['default' => $v]; })
                            ->end()
                            ->beforeNormalization()
                                ->ifTrue(function ($v) { return \is_array($v) && array_keys($v) === range(0, \count($v) - 1); })
                                ->then(function ($v) {
                                    $resources = [];
                                    foreach ($v as $resource) {
                                        $resources = array_merge_recursive(
                                            $resources,
                                            \is_array($resource) && isset($resource['name'])
                                                ? [$resource['name'] => $resource['value']]
                                                : ['default' => $resource]
                                        );
                                    }

                                    return $resources;
                                })
                            ->end()
                            ->prototype('array')
                                ->performNoDeepMerging()
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

    private function addMessengerSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('messenger')
                    ->info('Messenger configuration')
                    ->{!class_exists(FullStack::class) && interface_exists(MessageBusInterface::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->fixXmlConfig('transport')
                    ->fixXmlConfig('bus', 'buses')
                    ->validate()
                        ->ifTrue(function ($v) { return isset($v['buses']) && \count($v['buses']) > 1 && null === $v['default_bus']; })
                        ->thenInvalid('You must specify the "default_bus" if you define more than one bus.')
                    ->end()
                    ->validate()
                        ->ifTrue(static function ($v): bool { return isset($v['buses']) && null !== $v['default_bus'] && !isset($v['buses'][$v['default_bus']]); })
                        ->then(static function (array $v): void { throw new InvalidConfigurationException(sprintf('The specified default bus "%s" is not configured. Available buses are "%s".', $v['default_bus'], implode('", "', array_keys($v['buses'])))); })
                    ->end()
                    ->children()
                        ->arrayNode('routing')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('message_class')
                            ->beforeNormalization()
                                ->always()
                                ->then(function ($config) {
                                    if (!\is_array($config)) {
                                        return [];
                                    }
                                    // If XML config with only one routing attribute
                                    if (2 === \count($config) && isset($config['message-class']) && isset($config['sender'])) {
                                        $config = [0 => $config];
                                    }

                                    $newConfig = [];
                                    foreach ($config as $k => $v) {
                                        if (!\is_int($k)) {
                                            $newConfig[$k] = [
                                                'senders' => $v['senders'] ?? (\is_array($v) ? array_values($v) : [$v]),
                                            ];
                                        } else {
                                            $newConfig[$v['message-class']]['senders'] = array_map(
                                                function ($a) {
                                                    return \is_string($a) ? $a : $a['service'];
                                                },
                                                array_values($v['sender'])
                                            );
                                        }
                                    }

                                    return $newConfig;
                                })
                            ->end()
                            ->prototype('array')
                                ->performNoDeepMerging()
                                ->children()
                                    ->arrayNode('senders')
                                        ->requiresAtLeastOneElement()
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('serializer')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('default_serializer')
                                    ->defaultValue('messenger.transport.native_php_serializer')
                                    ->info('Service id to use as the default serializer for the transports.')
                                ->end()
                                ->arrayNode('symfony_serializer')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('format')->defaultValue('json')->info('Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default).')->end()
                                        ->arrayNode('context')
                                            ->normalizeKeys(false)
                                            ->useAttributeAsKey('name')
                                            ->defaultValue([])
                                            ->info('Context array for the messenger.transport.symfony_serializer service (which is not the serializer used by default).')
                                            ->prototype('variable')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('transports')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function (string $dsn) {
                                        return ['dsn' => $dsn];
                                    })
                                ->end()
                                ->fixXmlConfig('option')
                                ->children()
                                    ->scalarNode('dsn')->end()
                                    ->scalarNode('serializer')->defaultNull()->info('Service id of a custom serializer to use.')->end()
                                    ->arrayNode('options')
                                        ->normalizeKeys(false)
                                        ->defaultValue([])
                                        ->prototype('variable')
                                        ->end()
                                    ->end()
                                    ->arrayNode('retry_strategy')
                                        ->addDefaultsIfNotSet()
                                        ->beforeNormalization()
                                            ->always(function ($v) {
                                                if (isset($v['service']) && (isset($v['max_retries']) || isset($v['delay']) || isset($v['multiplier']) || isset($v['max_delay']))) {
                                                    throw new \InvalidArgumentException('The "service" cannot be used along with the other "retry_strategy" options.');
                                                }

                                                return $v;
                                            })
                                        ->end()
                                        ->children()
                                            ->scalarNode('service')->defaultNull()->info('Service id to override the retry strategy entirely')->end()
                                            ->integerNode('max_retries')->defaultValue(3)->min(0)->end()
                                            ->integerNode('delay')->defaultValue(1000)->min(0)->info('Time in ms to delay (or the initial value when multiplier is used)')->end()
                                            ->floatNode('multiplier')->defaultValue(2)->min(1)->info('If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries))')->end()
                                            ->integerNode('max_delay')->defaultValue(0)->min(0)->info('Max time in ms that a retry should ever be delayed (0 = infinite)')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('failure_transport')
                            ->defaultNull()
                            ->info('Transport name to send failed messages to (after all retries have failed).')
                        ->end()
                        ->scalarNode('default_bus')->defaultNull()->end()
                        ->arrayNode('buses')
                            ->defaultValue(['messenger.bus.default' => ['default_middleware' => true, 'middleware' => []]])
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->enumNode('default_middleware')
                                        ->values([true, false, 'allow_no_handlers'])
                                        ->defaultTrue()
                                    ->end()
                                    ->arrayNode('middleware')
                                        ->performNoDeepMerging()
                                        ->beforeNormalization()
                                            ->ifTrue(function ($v) { return \is_string($v) || (\is_array($v) && !\is_int(key($v))); })
                                            ->then(function ($v) { return [$v]; })
                                        ->end()
                                        ->defaultValue([])
                                        ->arrayPrototype()
                                            ->beforeNormalization()
                                                ->always()
                                                ->then(function ($middleware): array {
                                                    if (!\is_array($middleware)) {
                                                        return ['id' => $middleware];
                                                    }
                                                    if (isset($middleware['id'])) {
                                                        return $middleware;
                                                    }
                                                    if (1 < \count($middleware)) {
                                                        throw new \InvalidArgumentException('Invalid middleware at path "framework.messenger": a map with a single factory id as key and its arguments as value was expected, '.json_encode($middleware).' given.');
                                                    }

                                                    return [
                                                        'id' => key($middleware),
                                                        'arguments' => current($middleware),
                                                    ];
                                                })
                                            ->end()
                                            ->fixXmlConfig('argument')
                                            ->children()
                                                ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                                                ->arrayNode('arguments')
                                                    ->normalizeKeys(false)
                                                    ->defaultValue([])
                                                    ->prototype('variable')
                                                ->end()
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

    private function addRobotsIndexSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->booleanNode('disallow_search_engine_index')
                    ->info('Enabled by default when debug is enabled.')
                    ->defaultValue($this->debug)
                    ->treatNullLike($this->debug)
                ->end()
            ->end()
        ;
    }

    private function addHttpClientSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('http_client')
                    ->info('HTTP Client configuration')
                    ->{!class_exists(FullStack::class) && class_exists(HttpClient::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->fixXmlConfig('scoped_client')
                    ->children()
                        ->integerNode('max_host_connections')
                            ->info('The maximum number of connections to a single host.')
                        ->end()
                        ->arrayNode('default_options')
                            ->fixXmlConfig('header')
                            ->children()
                                ->arrayNode('headers')
                                    ->info('Associative array: header => value(s).')
                                    ->useAttributeAsKey('name')
                                    ->normalizeKeys(false)
                                    ->variablePrototype()->end()
                                ->end()
                                ->integerNode('max_redirects')
                                    ->info('The maximum number of redirects to follow.')
                                ->end()
                                ->scalarNode('http_version')
                                    ->info('The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.')
                                ->end()
                                ->arrayNode('resolve')
                                    ->info('Associative array: domain => IP.')
                                    ->useAttributeAsKey('host')
                                    ->beforeNormalization()
                                        ->always(function ($config) {
                                            if (!\is_array($config)) {
                                                return [];
                                            }
                                            if (!isset($config['host'], $config['value']) || \count($config) > 2) {
                                                return $config;
                                            }

                                            return [$config['host'] => $config['value']];
                                        })
                                    ->end()
                                    ->normalizeKeys(false)
                                    ->scalarPrototype()->end()
                                ->end()
                                ->scalarNode('proxy')
                                    ->info('The URL of the proxy to pass requests through or null for automatic detection.')
                                ->end()
                                ->scalarNode('no_proxy')
                                    ->info('A comma separated list of hosts that do not require a proxy to be reached.')
                                ->end()
                                ->floatNode('timeout')
                                    ->info('The idle timeout, defaults to the "default_socket_timeout" ini parameter.')
                                ->end()
                                ->floatNode('max_duration')
                                    ->info('The maximum execution time for the request+response as a whole.')
                                ->end()
                                ->scalarNode('bindto')
                                    ->info('A network interface name, IP address, a host name or a UNIX socket to bind to.')
                                ->end()
                                ->booleanNode('verify_peer')
                                    ->info('Indicates if the peer should be verified in an SSL/TLS context.')
                                ->end()
                                ->booleanNode('verify_host')
                                    ->info('Indicates if the host should exist as a certificate common name.')
                                ->end()
                                ->scalarNode('cafile')
                                    ->info('A certificate authority file.')
                                ->end()
                                ->scalarNode('capath')
                                    ->info('A directory that contains multiple certificate authority files.')
                                ->end()
                                ->scalarNode('local_cert')
                                    ->info('A PEM formatted certificate file.')
                                ->end()
                                ->scalarNode('local_pk')
                                    ->info('A private key file.')
                                ->end()
                                ->scalarNode('passphrase')
                                    ->info('The passphrase used to encrypt the "local_pk" file.')
                                ->end()
                                ->scalarNode('ciphers')
                                    ->info('A list of SSL/TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)')
                                ->end()
                                ->arrayNode('peer_fingerprint')
                                    ->info('Associative array: hashing algorithm => hash(es).')
                                    ->normalizeKeys(false)
                                    ->children()
                                        ->variableNode('sha1')->end()
                                        ->variableNode('pin-sha256')->end()
                                        ->variableNode('md5')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('scoped_clients')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->fixXmlConfig('header')
                                ->beforeNormalization()
                                    ->always()
                                    ->then(function ($config) {
                                        if (!class_exists(HttpClient::class)) {
                                            throw new LogicException('HttpClient support cannot be enabled as the component is not installed. Try running "composer require symfony/http-client".');
                                        }

                                        return \is_array($config) ? $config : ['base_uri' => $config];
                                    })
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($v) { return !isset($v['scope']) && !isset($v['base_uri']); })
                                    ->thenInvalid('Either "scope" or "base_uri" should be defined.')
                                ->end()
                                ->validate()
                                    ->ifTrue(function ($v) { return !empty($v['query']) && !isset($v['base_uri']); })
                                    ->thenInvalid('"query" applies to "base_uri" but no base URI is defined.')
                                ->end()
                                ->children()
                                    ->scalarNode('scope')
                                        ->info('The regular expression that the request URL must match before adding the other options. When none is provided, the base URI is used instead.')
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('base_uri')
                                        ->info('The URI to resolve relative URLs, following rules in RFC 3985, section 2.')
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('auth_basic')
                                        ->info('An HTTP Basic authentication "username:password".')
                                    ->end()
                                    ->scalarNode('auth_bearer')
                                        ->info('A token enabling HTTP Bearer authorization.')
                                    ->end()
                                    ->scalarNode('auth_ntlm')
                                        ->info('A "username:password" pair to use Microsoft NTLM authentication (requires the cURL extension).')
                                    ->end()
                                    ->arrayNode('query')
                                        ->info('Associative array of query string values merged with the base URI.')
                                        ->useAttributeAsKey('key')
                                        ->beforeNormalization()
                                            ->always(function ($config) {
                                                if (!\is_array($config)) {
                                                    return [];
                                                }
                                                if (!isset($config['key'], $config['value']) || \count($config) > 2) {
                                                    return $config;
                                                }

                                                return [$config['key'] => $config['value']];
                                            })
                                        ->end()
                                        ->normalizeKeys(false)
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->arrayNode('headers')
                                        ->info('Associative array: header => value(s).')
                                        ->useAttributeAsKey('name')
                                        ->normalizeKeys(false)
                                        ->variablePrototype()->end()
                                    ->end()
                                    ->integerNode('max_redirects')
                                        ->info('The maximum number of redirects to follow.')
                                    ->end()
                                    ->scalarNode('http_version')
                                        ->info('The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.')
                                    ->end()
                                    ->arrayNode('resolve')
                                        ->info('Associative array: domain => IP.')
                                        ->useAttributeAsKey('host')
                                        ->beforeNormalization()
                                            ->always(function ($config) {
                                                if (!\is_array($config)) {
                                                    return [];
                                                }
                                                if (!isset($config['host'], $config['value']) || \count($config) > 2) {
                                                    return $config;
                                                }

                                                return [$config['host'] => $config['value']];
                                            })
                                        ->end()
                                        ->normalizeKeys(false)
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->scalarNode('proxy')
                                        ->info('The URL of the proxy to pass requests through or null for automatic detection.')
                                    ->end()
                                    ->scalarNode('no_proxy')
                                        ->info('A comma separated list of hosts that do not require a proxy to be reached.')
                                    ->end()
                                    ->floatNode('timeout')
                                        ->info('The idle timeout, defaults to the "default_socket_timeout" ini parameter.')
                                    ->end()
                                    ->floatNode('max_duration')
                                        ->info('The maximum execution time for the request+response as a whole.')
                                    ->end()
                                    ->scalarNode('bindto')
                                        ->info('A network interface name, IP address, a host name or a UNIX socket to bind to.')
                                    ->end()
                                    ->booleanNode('verify_peer')
                                        ->info('Indicates if the peer should be verified in an SSL/TLS context.')
                                    ->end()
                                    ->booleanNode('verify_host')
                                        ->info('Indicates if the host should exist as a certificate common name.')
                                    ->end()
                                    ->scalarNode('cafile')
                                        ->info('A certificate authority file.')
                                    ->end()
                                    ->scalarNode('capath')
                                        ->info('A directory that contains multiple certificate authority files.')
                                    ->end()
                                    ->scalarNode('local_cert')
                                        ->info('A PEM formatted certificate file.')
                                    ->end()
                                    ->scalarNode('local_pk')
                                        ->info('A private key file.')
                                    ->end()
                                    ->scalarNode('passphrase')
                                        ->info('The passphrase used to encrypt the "local_pk" file.')
                                    ->end()
                                    ->scalarNode('ciphers')
                                        ->info('A list of SSL/TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)')
                                    ->end()
                                    ->arrayNode('peer_fingerprint')
                                        ->info('Associative array: hashing algorithm => hash(es).')
                                        ->normalizeKeys(false)
                                        ->children()
                                            ->variableNode('sha1')->end()
                                            ->variableNode('pin-sha256')->end()
                                            ->variableNode('md5')->end()
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

    private function addMailerSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('mailer')
                    ->info('Mailer configuration')
                    ->{!class_exists(FullStack::class) && class_exists(Mailer::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->validate()
                        ->ifTrue(function ($v) { return isset($v['dsn']) && \count($v['transports']); })
                        ->thenInvalid('"dsn" and "transports" cannot be used together.')
                    ->end()
                    ->fixXmlConfig('transport')
                    ->children()
                        ->scalarNode('dsn')->defaultNull()->end()
                        ->arrayNode('transports')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('envelope')
                            ->info('Mailer Envelope configuration')
                            ->children()
                                ->scalarNode('sender')->end()
                                ->arrayNode('recipients')
                                    ->performNoDeepMerging()
                                    ->beforeNormalization()
                                    ->ifArray()
                                        ->then(function ($v) {
                                            return array_filter(array_values($v));
                                        })
                                    ->end()
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
