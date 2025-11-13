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

use Doctrine\DBAL\Connection;
use Psr\Log\LogLevel;
use Seld\JsonLint\JsonParser;
use Symfony\Bundle\FullStack;
use Symfony\Component\Asset\Package;
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\Compressor\CompressorInterface;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Finder\Glob;
use Symfony\Component\Form\Form;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Semaphore\Semaphore;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\Translator;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Webhook\Controller\WebhookController;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WorkflowEvents;

/**
 * FrameworkExtension configuration structure.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @param bool $debug Whether debugging is enabled or not
     */
    public function __construct(
        private bool $debug,
    ) {
    }

    /**
     * Generates the configuration tree builder.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('framework');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->docUrl('https://symfony.com/doc/{version:major}.{version:minor}/reference/configuration/framework.html', 'symfony/framework-bundle')
            ->beforeNormalization()
                ->ifArray()
                ->then(static function ($v) {
                    if (isset($v['templating']) && class_exists(Package::class)) {
                        $v['assets'] ??= [];
                    }

                    return $v;
                })
            ->end()
            ->children()
                ->scalarNode('secret')->end()
                ->booleanNode('http_method_override')
                    ->info("Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests.")
                    ->defaultFalse()
                ->end()
                ->arrayNode('allowed_http_method_override')
                    ->info('Sets the list of HTTP methods that can be overridden. Set to null to allow all methods to be overridden (default). Set to an empty array to disallow overrides entirely. Otherwise, provide the list of uppercased method names that are allowed.')
                    ->stringPrototype()->end()
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn ($v) => array_intersect($v, ['GET', 'HEAD', 'CONNECT', 'TRACE']))
                        ->thenInvalid('The HTTP methods "GET", "HEAD", "CONNECT", and "TRACE" cannot be overridden.')
                    ->end()
                ->end()
                ->scalarNode('trust_x_sendfile_type_header')
                    ->info('Set true to enable support for xsendfile in binary file responses.')
                    ->defaultValue('%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%')
                ->end()
                ->scalarNode('ide')->defaultValue($this->debug ? '%env(default::SYMFONY_IDE)%' : null)->end()
                ->booleanNode('test')->end()
                ->scalarNode('default_locale')->defaultValue('en')->end()
                ->booleanNode('set_locale_from_accept_language')
                    ->info('Whether to use the Accept-Language HTTP header to set the Request locale (only when the "_locale" request attribute is not passed).')
                    ->defaultFalse()
                ->end()
                ->booleanNode('set_content_language_from_locale')
                    ->info('Whether to set the Content-Language HTTP header on the Response using the Request locale.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('enabled_locales', 'enabled_locale')
                    ->info('Defines the possible locales for the application. This list is used for generating translations files, but also to restrict which locales are allowed when it is set from Accept-Language header (using "set_locale_from_accept_language").')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('trusted_hosts')
                    ->beforeNormalization()->ifString()->then(static fn ($v) => $v ? [$v] : [])->end()
                    ->prototype('scalar')->end()
                    ->defaultValue(['%env(default::SYMFONY_TRUSTED_HOSTS)%'])
                ->end()
                ->variableNode('trusted_proxies')
                    ->beforeNormalization()
                        ->ifTrue(static fn ($v) => 'private_ranges' === $v || 'PRIVATE_SUBNETS' === $v)
                        ->then(static fn () => IpUtils::PRIVATE_SUBNETS)
                    ->end()
                    ->defaultValue(['%env(default::SYMFONY_TRUSTED_PROXIES)%'])
                ->end()
                ->arrayNode('trusted_headers', 'trusted_header')
                    ->performNoDeepMerging()
                    ->beforeNormalization()->ifString()->then(static fn ($v) => $v ? [$v] : [])->end()
                    ->prototype('scalar')->end()
                    ->defaultValue(['%env(default::SYMFONY_TRUSTED_HEADERS)%'])
                ->end()
                ->scalarNode('error_controller')
                    ->defaultValue('error_controller')
                ->end()
                ->booleanNode('handle_all_throwables')->info('HttpKernel will handle all kinds of \Throwable.')->defaultTrue()->end()
            ->end()
        ;

        $willBeAvailable = static function (string $package, string $class, ?string $parentPackage = null) {
            $parentPackages = (array) $parentPackage;
            $parentPackages[] = 'symfony/framework-bundle';

            return ContainerBuilder::willBeAvailable($package, $class, $parentPackages);
        };

        $enableIfStandalone = static fn (string $package, string $class) => !class_exists(FullStack::class) && $willBeAvailable($package, $class) ? 'canBeDisabled' : 'canBeEnabled';

        $this->addCsrfSection($rootNode);
        $this->addFormSection($rootNode, $enableIfStandalone);
        $this->addHttpCacheSection($rootNode);
        $this->addEsiSection($rootNode);
        $this->addSsiSection($rootNode);
        $this->addFragmentsSection($rootNode);
        $this->addProfilerSection($rootNode);
        $this->addWorkflowSection($rootNode);
        $this->addRouterSection($rootNode);
        $this->addSessionSection($rootNode);
        $this->addRequestSection($rootNode);
        $this->addAssetsSection($rootNode, $enableIfStandalone);
        $this->addAssetMapperSection($rootNode, $enableIfStandalone);
        $this->addTranslatorSection($rootNode, $enableIfStandalone);
        $this->addValidationSection($rootNode, $enableIfStandalone);
        $this->addAnnotationsSection($rootNode);
        $this->addSerializerSection($rootNode, $enableIfStandalone);
        $this->addPropertyAccessSection($rootNode, $willBeAvailable);
        $this->addTypeInfoSection($rootNode, $enableIfStandalone);
        $this->addPropertyInfoSection($rootNode, $enableIfStandalone);
        $this->addCacheSection($rootNode, $willBeAvailable);
        $this->addPhpErrorsSection($rootNode);
        $this->addExceptionsSection($rootNode);
        $this->addWebLinkSection($rootNode, $enableIfStandalone);
        $this->addLockSection($rootNode, $enableIfStandalone);
        $this->addSemaphoreSection($rootNode, $enableIfStandalone);
        $this->addMessengerSection($rootNode, $enableIfStandalone);
        $this->addSchedulerSection($rootNode, $enableIfStandalone);
        $this->addRobotsIndexSection($rootNode);
        $this->addHttpClientSection($rootNode, $enableIfStandalone);
        $this->addMailerSection($rootNode, $enableIfStandalone);
        $this->addSecretsSection($rootNode);
        $this->addNotifierSection($rootNode, $enableIfStandalone);
        $this->addRateLimiterSection($rootNode, $enableIfStandalone);
        $this->addUidSection($rootNode, $enableIfStandalone);
        $this->addHtmlSanitizerSection($rootNode, $enableIfStandalone);
        $this->addWebhookSection($rootNode, $enableIfStandalone);
        $this->addRemoteEventSection($rootNode, $enableIfStandalone);
        $this->addJsonStreamerSection($rootNode, $enableIfStandalone);

        return $treeBuilder;
    }

    private function addSecretsSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('secrets')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('vault_directory')->defaultValue('%kernel.project_dir%/config/secrets/%kernel.runtime_environment%')->cannotBeEmpty()->end()
                        ->scalarNode('local_dotenv_file')->defaultValue('%kernel.project_dir%/.env.%kernel.runtime_environment%.local')->end()
                        ->scalarNode('decryption_env_var')->defaultValue('base64:default::SYMFONY_DECRYPTION_SECRET')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addCsrfSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('csrf_protection')
                    ->treatFalseLike(['enabled' => false])
                    ->treatTrueLike(['enabled' => true])
                    ->treatNullLike(['enabled' => true])
                    ->addDefaultsIfNotSet()
                    ->children()
                        // defaults to (framework.csrf_protection.stateless_token_ids || framework.session.enabled) && !class_exists(FullStack::class) && interface_exists(CsrfTokenManagerInterface::class)
                        ->scalarNode('enabled')->defaultNull()->end()
                        ->arrayNode('stateless_token_ids', 'stateless_token_id')
                            ->scalarPrototype()->end()
                            ->info('Enable headers/cookies-based CSRF validation for the listed token ids.')
                        ->end()
                        ->scalarNode('check_header')
                            ->defaultFalse()
                            ->info('Whether to check the CSRF token in a header in addition to a cookie when using stateless protection.')
                        ->end()
                        ->scalarNode('cookie_name')
                            ->defaultValue('csrf-token')
                            ->info('The name of the cookie to use when using stateless protection.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addFormSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('form')
                    ->info('Form configuration')
                    ->{$enableIfStandalone('symfony/form', Form::class)}()
                    ->children()
                        ->arrayNode('csrf_protection')
                            ->treatFalseLike(['enabled' => false])
                            ->treatTrueLike(['enabled' => true])
                            ->treatNullLike(['enabled' => true])
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('enabled')->defaultNull()->end() // defaults to framework.csrf_protection.enabled
                                ->scalarNode('token_id')->defaultNull()->end()
                                ->scalarNode('field_name')->defaultValue('_token')->end()
                                ->arrayNode('field_attr')
                                    ->performNoDeepMerging()
                                    ->normalizeKeys(false)
                                    ->useAttributeAsKey('name')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['data-controller' => 'csrf-protection'])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addHttpCacheSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('http_cache')
                    ->info('HTTP cache configuration')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                        ->enumNode('trace_level')
                            ->values(['none', 'short', 'full'])
                        ->end()
                        ->scalarNode('trace_header')->end()
                        ->integerNode('default_ttl')->end()
                        ->arrayNode('private_headers', 'private_header')
                            ->performNoDeepMerging()
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('skip_response_headers', 'skip_response_header')
                            ->performNoDeepMerging()
                            ->scalarPrototype()->end()
                        ->end()
                        ->booleanNode('allow_reload')->end()
                        ->booleanNode('allow_revalidate')->end()
                        ->integerNode('stale_while_revalidate')->end()
                        ->integerNode('stale_if_error')->end()
                        ->booleanNode('terminate_on_cache_hit')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addEsiSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('esi')
                    ->info('ESI configuration')
                    ->canBeEnabled()
                ->end()
            ->end()
        ;
    }

    private function addSsiSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('ssi')
                    ->info('SSI configuration')
                    ->canBeEnabled()
                ->end()
            ->end();
    }

    private function addFragmentsSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('fragments')
                    ->info('Fragments configuration')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('hinclude_default_template')->defaultNull()->end()
                        ->scalarNode('path')->defaultValue('/_fragment')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addProfilerSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('profiler')
                    ->info('Profiler configuration')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('collect')->defaultTrue()->end()
                        ->scalarNode('collect_parameter')->defaultNull()->info('The name of the parameter to use to enable or disable collection on a per request basis.')->end()
                        ->booleanNode('only_exceptions')->defaultFalse()->end()
                        ->booleanNode('only_main_requests')->defaultFalse()->end()
                        ->scalarNode('dsn')->defaultValue('file:%kernel.cache_dir%/profiler')->end()
                        ->booleanNode('collect_serializer_data')->info('Enables the serializer data collector and profiler panel.')->defaultFalse()->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addWorkflowSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('workflows', 'workflow')
                    ->canBeEnabled()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($v) {
                            if (true === ($v['enabled'] ?? false)) {
                                $workflows = $v;
                                unset($workflows['enabled']);

                                if (1 === \count($workflows) && isset($workflows[0]['enabled']) && 1 === \count($workflows[0])) {
                                    $workflows = [];
                                }

                                if (1 === \count($workflows) && isset($workflows['workflows']) && !array_is_list($workflows['workflows']) && array_diff_key($workflows['workflows'], ['audit_trail' => 1, 'type' => 1, 'marking_store' => 1, 'supports' => 1, 'support_strategy' => 1, 'initial_marking' => 1, 'places' => 1, 'transitions' => 1])) {
                                    $workflows = $workflows['workflows'];
                                }

                                foreach ($workflows as $key => $workflow) {
                                    if (isset($workflow['enabled']) && false === $workflow['enabled']) {
                                        throw new LogicException(\sprintf('Cannot disable a single workflow. Remove the configuration for the workflow "%s" instead.', $key));
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
                        ->arrayNode('workflows', 'workflow')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->children()
                                    ->arrayNode('audit_trail')
                                        ->canBeEnabled()
                                    ->end()
                                    ->enumNode('type')
                                        ->values(['workflow', 'state_machine'])
                                        ->defaultValue('state_machine')
                                    ->end()
                                    ->arrayNode('marking_store')
                                        ->children()
                                            ->enumNode('type')
                                                ->values(['method'])
                                            ->end()
                                            ->scalarNode('property')
                                                ->cannotBeEmpty()
                                            ->end()
                                            ->scalarNode('service')
                                                ->cannotBeEmpty()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('supports', 'support')
                                        ->acceptAndWrap(['string'])
                                        ->prototype('scalar')
                                            ->cannotBeEmpty()
                                            ->validate()
                                                ->ifTrue(static fn ($v) => !class_exists($v) && !interface_exists($v, false))
                                                ->thenInvalid('The supported class or interface "%s" does not exist.')
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('definition_validators', 'definition_validator')
                                        ->prototype('scalar')
                                            ->cannotBeEmpty()
                                            ->validate()
                                                ->ifTrue(static fn ($v) => !class_exists($v))
                                                ->thenInvalid('The validation class %s does not exist.')
                                            ->end()
                                            ->validate()
                                                ->ifTrue(static fn ($v) => !is_a($v, DefinitionValidatorInterface::class, true))
                                                ->thenInvalid(\sprintf('The validation class %%s is not an instance of "%s".', DefinitionValidatorInterface::class))
                                            ->end()
                                            ->validate()
                                                ->ifTrue(static fn ($v) => 1 <= (new \ReflectionClass($v))->getConstructor()?->getNumberOfRequiredParameters())
                                                ->thenInvalid('The %s validation class constructor must not have any arguments.')
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->scalarNode('support_strategy')
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->arrayNode('initial_marking')
                                        ->acceptAndWrap(['string'])
                                        ->defaultValue([])
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->arrayNode('events_to_dispatch', 'event_to_dispatch')
                                        ->defaultNull()
                                        ->stringPrototype()->end()
                                        ->validate()
                                            ->ifTrue(static function ($v) {
                                                if (!class_exists(WorkflowEvents::class)) {
                                                    return false;
                                                }

                                                foreach ($v as $value) {
                                                    if (!\in_array($value, WorkflowEvents::ALIASES, true)) {
                                                        return true;
                                                    }
                                                }

                                                return false;
                                            })
                                            ->thenInvalid('The value must be "null" or an array of workflow events (like ["workflow.enter"]).')
                                        ->end()
                                        ->info('Select which Transition events should be dispatched for this Workflow.')
                                        ->example(['workflow.enter', 'workflow.transition'])
                                    ->end()
                                    ->arrayNode('places', 'place')
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(static function ($places) {
                                                if (2 !== \count($places = explode('::', $places, 2))) {
                                                    throw new InvalidConfigurationException('The "places" option must be a "FQCN::glob" pattern in workflow configuration.');
                                                }
                                                [$class, $pattern] = $places;
                                                if (!class_exists($class) && !interface_exists($class, false)) {
                                                    throw new InvalidConfigurationException(\sprintf('The "places" option must be a "FQCN::glob" pattern in workflow configuration, but class "%s" is not found.', $class));
                                                }

                                                $places = [];
                                                $regex = Glob::toRegex($pattern, false);

                                                foreach ((new \ReflectionClass($class))->getConstants() as $name => $value) {
                                                    if (preg_match($regex, $name)) {
                                                        $places[] = $value;
                                                    }
                                                }

                                                return $places ?: throw new InvalidConfigurationException(\sprintf('No places found for pattern "%s::%s" in workflow configuration.', $class, $pattern));
                                            })
                                        ->end()
                                        ->beforeNormalization()
                                            ->ifArray()
                                            ->then(static function ($places) {
                                                $normalizedPlaces = [];
                                                foreach ($places as $key => $value) {
                                                    if ($value instanceof \BackedEnum) {
                                                        $value = ['name' => $value->value];
                                                    } elseif (!\is_array($value)) {
                                                        $value = ['name' => $value];
                                                    }
                                                    $value['name'] ??= $key;
                                                    $normalizedPlaces[] = $value;
                                                }

                                                return $normalizedPlaces;
                                            })
                                        ->end()
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
                                    ->arrayNode('transitions', 'transition')
                                        ->beforeNormalization()
                                            ->ifArray()
                                            ->then(static function ($transitions) {
                                                $normalizedTransitions = [];
                                                foreach ($transitions as $key => $transition) {
                                                    if (\is_array($transition)) {
                                                        if (\is_string($key = $transition['key'] ?? $key)) {
                                                            $transition['name'] ??= $key;
                                                        }
                                                        if (!($transition['name'] ?? false)) {
                                                            throw new InvalidConfigurationException('The "name" option is required for each transition in workflow configuration.');
                                                        }
                                                        unset($transition['key']);
                                                    }
                                                    $normalizedTransitions[$key] = $transition;
                                                }

                                                return $normalizedTransitions;
                                            })
                                        ->end()
                                        ->isRequired()
                                        ->requiresAtLeastOneElement()
                                        ->prototype('array')
                                            ->children()
                                                ->stringNode('name')
                                                    ->isRequired()
                                                    ->cannotBeEmpty()
                                                ->end()
                                                ->stringNode('guard')
                                                    ->cannotBeEmpty()
                                                    ->info('An expression to block the transition.')
                                                    ->example('is_fully_authenticated() and is_granted(\'ROLE_JOURNALIST\') and subject.getTitle() == \'My first article\'')
                                                ->end()
                                                ->arrayNode('from')
                                                    ->performNoDeepMerging()
                                                    ->acceptAndWrap(['backed-enum', 'string'])
                                                    ->beforeNormalization()
                                                        ->ifArray()
                                                        ->then($workflowNormalizeArcs = static function ($arcs) {
                                                            // Fix XML parsing, when only one arc is defined
                                                            if (\array_key_exists('value', $arcs) && \array_key_exists('weight', $arcs)) {
                                                                $arcs = [[
                                                                    'place' => $arcs['value'],
                                                                    'weight' => $arcs['weight'],
                                                                ]];
                                                            } elseif (\array_key_exists('place', $arcs)) {
                                                                $arcs = [$arcs];
                                                            }

                                                            $normalizedArcs = [];
                                                            foreach ($arcs as $arc) {
                                                                if (\is_string($arc) || $arc instanceof \BackedEnum) {
                                                                    $arc = ['place' => $arc];
                                                                } elseif (!\is_array($arc)) {
                                                                    throw new InvalidConfigurationException('The "from" arcs must be a list of strings or arrays in workflow configuration.');
                                                                } elseif (\array_key_exists('value', $arc) && \array_key_exists('weight', $arc)) {
                                                                    // Fix XML parsing
                                                                    $arc = [
                                                                        'place' => $arc['value'],
                                                                        'weight' => $arc['weight'],
                                                                    ];
                                                                }

                                                                if (($arc['place'] ?? null) instanceof \BackedEnum) {
                                                                    $arc['place'] = $arc['place']->value;
                                                                }

                                                                $normalizedArcs[] = $arc;
                                                            }

                                                            return $normalizedArcs;
                                                        })
                                                    ->end()
                                                    ->requiresAtLeastOneElement()
                                                    ->prototype('array')
                                                        ->children()
                                                            ->stringNode('place')
                                                                ->isRequired()
                                                                ->cannotBeEmpty()
                                                            ->end()
                                                            ->integerNode('weight')
                                                                ->defaultValue(1)
                                                                ->min(1)
                                                            ->end()
                                                        ->end()
                                                    ->end()
                                                ->end()
                                                ->arrayNode('to')
                                                    ->performNoDeepMerging()
                                                    ->acceptAndWrap(['backed-enum', 'string'])
                                                    ->beforeNormalization()
                                                        ->ifArray()
                                                        ->then($workflowNormalizeArcs)
                                                    ->end()
                                                    ->requiresAtLeastOneElement()
                                                    ->prototype('array')
                                                        ->children()
                                                            ->stringNode('place')
                                                                ->isRequired()
                                                                ->cannotBeEmpty()
                                                            ->end()
                                                            ->integerNode('weight')
                                                                ->defaultValue(1)
                                                                ->min(1)
                                                            ->end()
                                                        ->end()
                                                    ->end()
                                                ->end()
                                                ->integerNode('weight')
                                                    ->defaultValue(1)
                                                    ->validate()
                                                        ->ifTrue(static fn ($v) => $v < 1)
                                                        ->thenInvalid('The weight must be greater than 0.')
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
                                    ->ifTrue(static fn ($v) => $v['supports'] && isset($v['support_strategy']))
                                    ->thenInvalid('"supports" and "support_strategy" cannot be used together.')
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn ($v) => !$v['supports'] && !isset($v['support_strategy']))
                                    ->thenInvalid('"supports" or "support_strategy" should be configured.')
                                ->end()
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static function ($values) {
                                        // Special case to deal with XML when the user wants an empty array
                                        if (\array_key_exists('event_to_dispatch', $values) && null === $values['event_to_dispatch']) {
                                            $values['events_to_dispatch'] = [];
                                            unset($values['event_to_dispatch']);
                                        }

                                        return $values;
                                    })
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addRouterSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('router')
                    ->info('Router configuration')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('resource')->isRequired()->end()
                        ->scalarNode('type')->end()
                        ->scalarNode('cache_dir')
                            ->defaultValue('%kernel.build_dir%')
                            ->setDeprecated('symfony/framework-bundle', '7.1', 'Setting the "%path%.%node%" configuration option is deprecated. It will be removed in version 8.0.')
                        ->end()
                        ->scalarNode('default_uri')
                            ->info('The default URI used to generate URLs in a non-HTTP context.')
                            ->defaultNull()
                        ->end()
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
                        ->booleanNode('utf8')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addSessionSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('session')
                    ->info('Session configuration')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('storage_factory_id')->defaultValue('session.storage.factory.native')->end()
                        ->scalarNode('handler_id')
                            ->info('Defaults to using the native session handler, or to the native *file* session handler if "save_path" is not null.')
                        ->end()
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
                        ->enumNode('cookie_secure')->values([true, false, 'auto'])->defaultValue('auto')->end()
                        ->booleanNode('cookie_httponly')->defaultTrue()->end()
                        ->enumNode('cookie_samesite')->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE])->defaultValue('lax')->end()
                        ->booleanNode('use_cookies')->end()
                        ->scalarNode('gc_divisor')->end()
                        ->scalarNode('gc_probability')->end()
                        ->scalarNode('gc_maxlifetime')->end()
                        ->scalarNode('save_path')
                            ->info('Defaults to "%kernel.cache_dir%/sessions" if the "handler_id" option is not null.')
                        ->end()
                        ->integerNode('metadata_update_threshold')
                            ->defaultValue(0)
                            ->info('Seconds to wait between 2 session metadata updates.')
                        ->end()
                        ->integerNode('sid_length')
                            ->min(22)
                            ->max(256)
                            ->setDeprecated('symfony/framework-bundle', '7.2', 'Setting the "%path%.%node%" configuration option is deprecated. It will be removed in version 8.0. No alternative is provided as PHP 8.4 has deprecated the related option.')
                        ->end()
                        ->integerNode('sid_bits_per_character')
                            ->min(4)
                            ->max(6)
                            ->setDeprecated('symfony/framework-bundle', '7.2', 'Setting the "%path%.%node%" configuration option is deprecated. It will be removed in version 8.0. No alternative is provided as PHP 8.4 has deprecated the related option.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addRequestSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('request')
                    ->info('Request configuration')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('formats', 'format')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->acceptAndWrap(['string'])
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static fn ($v) => (array) ($v['mime_type'] ?? $v))
                                ->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addAssetsSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('assets')
                    ->info('Assets configuration')
                    ->{$enableIfStandalone('symfony/asset', Package::class)}()
                    ->children()
                        ->booleanNode('strict_mode')
                            ->info('Throw an exception if an entry is missing from the manifest.json.')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('version_strategy')->defaultNull()->end()
                        ->scalarNode('version')->defaultNull()->end()
                        ->scalarNode('version_format')->defaultValue('%%s?%%s')->end()
                        ->scalarNode('json_manifest_path')->defaultNull()->end()
                        ->scalarNode('base_path')->defaultValue('')->end()
                        ->arrayNode('base_urls', 'base_url')
                            ->requiresAtLeastOneElement()
                            ->acceptAndWrap(['string'])
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
                    ->children()
                        ->arrayNode('packages', 'package')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->children()
                                    ->booleanNode('strict_mode')
                                        ->info('Throw an exception if an entry is missing from the manifest.json.')
                                        ->defaultFalse()
                                    ->end()
                                    ->scalarNode('version_strategy')->defaultNull()->end()
                                    ->scalarNode('version')
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(static fn ($v) => '' === $v ? null : $v)
                                        ->end()
                                    ->end()
                                    ->scalarNode('version_format')->defaultNull()->end()
                                    ->scalarNode('json_manifest_path')->defaultNull()->end()
                                    ->scalarNode('base_path')->defaultValue('')->end()
                                    ->arrayNode('base_urls', 'base_url')
                                        ->requiresAtLeastOneElement()
                                        ->acceptAndWrap(['string'])
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

    private function addAssetMapperSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('asset_mapper')
                    ->info('Asset Mapper configuration')
                    ->{$enableIfStandalone('symfony/asset-mapper', AssetMapper::class)}()
                    ->children()
                        // add array node called "paths" that will be an array of strings
                        ->arrayNode('paths', 'path')
                            ->info('Directories that hold assets that should be in the mapper. Can be a simple array of an array of ["path/to/assets": "namespace"].')
                            ->example(['assets/'])
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('namespace')
                            ->acceptAndWrap(['string'])
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(static function ($v) {
                                    $result = [];
                                    foreach ($v as $key => $item) {
                                        // "dir" => "namespace"
                                        if (\is_string($key)) {
                                            $result[$key] = $item;

                                            continue;
                                        }

                                        if (\is_array($item)) {
                                            // $item = ["namespace" => "the/namespace", "value" => "the/dir"]
                                            $result[$item['value']] = $item['namespace'] ?? '';
                                        } else {
                                            // $item = "the/dir"
                                            $result[$item] = '';
                                        }
                                    }

                                    return $result;
                                })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('excluded_patterns', 'excluded_pattern')
                            ->info('Array of glob patterns of asset file paths that should not be in the asset mapper.')
                            ->prototype('scalar')->end()
                            ->example(['*/assets/build/*', '*/*_.scss'])
                        ->end()
                        // boolean called defaulting to true
                        ->booleanNode('exclude_dotfiles')
                            ->info('If true, any files starting with "." will be excluded from the asset mapper.')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('server')
                            ->info('If true, a "dev server" will return the assets from the public directory (true in "debug" mode only by default).')
                            ->defaultValue($this->debug)
                        ->end()
                        ->scalarNode('public_prefix')
                            ->info('The public path where the assets will be written to (and served from when "server" is true).')
                            ->defaultValue('/assets/')
                        ->end()
                        ->enumNode('missing_import_mode')
                            ->values(['strict', 'warn', 'ignore'])
                            ->info('Behavior if an asset cannot be found when imported from JavaScript or CSS files - e.g. "import \'./non-existent.js\'". "strict" means an exception is thrown, "warn" means a warning is logged, "ignore" means the import is left as-is.')
                            ->defaultValue('warn')
                        ->end()
                        ->arrayNode('extensions', 'extension')
                            ->info('Key-value pair of file extensions set to their mime type.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('extension')
                            ->example(['.zip' => 'application/zip'])
                            ->prototype('scalar')->end()
                        ->end()
                        ->scalarNode('importmap_path')
                            ->info('The path of the importmap.php file.')
                            ->defaultValue('%kernel.project_dir%/importmap.php')
                        ->end()
                        ->scalarNode('importmap_polyfill')
                            ->info('The importmap name that will be used to load the polyfill. Set to false to disable.')
                            ->validate()
                                ->ifTrue()
                                ->thenInvalid('Invalid "importmap_polyfill" value. Must be either an importmap name or false.')
                            ->end()
                            ->defaultValue('es-module-shims')
                        ->end()
                        ->arrayNode('importmap_script_attributes', 'importmap_script_attribute')
                            ->info('Key-value pair of attributes to add to script tags output for the importmap.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('key')
                            ->example(['data-turbo-track' => 'reload'])
                            ->prototype('scalar')->end()
                        ->end()
                        ->scalarNode('vendor_dir')
                            ->info('The directory to store JavaScript vendors.')
                            ->defaultValue('%kernel.project_dir%/assets/vendor')
                        ->end()
                        ->arrayNode('precompress')
                            ->info('Precompress assets with Brotli, Zstandard and gzip.')
                            ->canBeEnabled()
                            ->children()
                                ->arrayNode('formats', 'format')
                                    ->info('Array of formats to enable. "brotli", "zstandard" and "gzip" are supported. Defaults to all formats supported by the system. The entire list must be provided.')
                                    ->prototype('scalar')->end()
                                    ->performNoDeepMerging()
                                    ->validate()
                                        ->ifTrue(static fn ($v) => array_diff($v, ['brotli', 'zstandard', 'gzip']))
                                        ->thenInvalid('Unsupported format: "brotli", "zstandard" and "gzip" are supported.')
                                    ->end()
                                ->end()
                                ->arrayNode('extensions', 'extension')
                                    ->info('Array of extensions to compress. The entire list must be provided, no merging occurs.')
                                    ->prototype('scalar')->end()
                                    ->performNoDeepMerging()
                                    ->defaultValue(interface_exists(CompressorInterface::class) ? CompressorInterface::DEFAULT_EXTENSIONS : [])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addTranslatorSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('translator')
                    ->info('Translator configuration')
                    ->{$enableIfStandalone('symfony/translation', Translator::class)}()
                    ->children()
                        ->arrayNode('fallbacks', 'fallback')
                            ->info('Defaults to the value of "default_locale".')
                            ->acceptAndWrap(['string'])
                            ->prototype('scalar')->end()
                            ->defaultValue([])
                        ->end()
                        ->booleanNode('logging')->defaultFalse()->end()
                        ->scalarNode('formatter')->defaultValue('translator.formatter.default')->end()
                        ->scalarNode('cache_dir')->defaultValue('%kernel.cache_dir%/translations')->end()
                        ->scalarNode('default_path')
                            ->info('The default path used to load translations.')
                            ->defaultValue('%kernel.project_dir%/translations')
                        ->end()
                        ->arrayNode('paths', 'path')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('pseudo_localization')
                            ->canBeEnabled()
                            ->children()
                                ->booleanNode('accents')->defaultTrue()->end()
                                ->floatNode('expansion_factor')
                                    ->min(1.0)
                                    ->defaultValue(1.0)
                                ->end()
                                ->booleanNode('brackets')->defaultTrue()->end()
                                ->booleanNode('parse_html')->defaultFalse()->end()
                                ->arrayNode('localizable_html_attributes', 'localizable_html_attribute')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('providers', 'provider')
                            ->info('Translation providers you can read/write your translations from.')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('dsn')->end()
                                    ->arrayNode('domains', 'domain')
                                        ->prototype('scalar')->end()
                                        ->defaultValue([])
                                    ->end()
                                    ->arrayNode('locales', 'locale')
                                        ->prototype('scalar')->end()
                                        ->defaultValue([])
                                        ->info('If not set, all locales listed under framework.enabled_locales are used.')
                                    ->end()
                                ->end()
                            ->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('globals', 'global')
                            ->info('Global parameters.')
                            ->example(['app_version' => 3.14])
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->acceptAndWrap(['string'], 'value')
                                ->children()
                                    ->variableNode('value')->end()
                                    ->stringNode('message')->end()
                                    ->arrayNode('parameters', 'parameter')
                                        ->normalizeKeys(false)
                                        ->useAttributeAsKey('name')
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->stringNode('domain')->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn ($v) => !(isset($v['value']) xor isset($v['message'])))
                                    ->thenInvalid('The "globals" parameter should be either a string or an array with a "value" or a "message" key')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addValidationSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('validation')
                    ->info('Validation configuration')
                    ->{$enableIfStandalone('symfony/validator', Validation::class)}()
                    ->children()
                        ->scalarNode('cache')
                            ->setDeprecated('symfony/framework-bundle', '7.3', 'Setting the "%path%.%node%" configuration option is deprecated. It will be removed in version 8.0.')
                        ->end()
                        ->booleanNode('enable_attributes')->{class_exists(FullStack::class) ? 'defaultFalse' : 'defaultTrue'}()->end()
                        ->arrayNode('static_method')
                            ->acceptAndWrap(['string'])
                            ->defaultValue(['loadValidatorMetadata'])
                            ->prototype('scalar')->end()
                            ->treatFalseLike([])
                        ->end()
                        ->scalarNode('translation_domain')->defaultValue('validators')->end()
                        ->enumNode('email_validation_mode')->values(['html5', 'html5-allow-no-tld', 'strict', 'loose'])->defaultValue('html5')->end()
                        ->arrayNode('mapping')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('paths', 'path')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('not_compromised_password')
                            ->canBeDisabled('When disabled, compromised passwords will be accepted as valid.')
                            ->children()
                                ->scalarNode('endpoint')
                                    ->defaultNull()
                                    ->info('API endpoint for the NotCompromisedPassword Validator.')
                                ->end()
                            ->end()
                        ->end()
                        ->booleanNode('disable_translation')
                            ->defaultFalse()
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
                                ->then(static function ($values) {
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
                                ->children()
                                    ->arrayNode('services', 'service')
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

    private function addAnnotationsSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('annotations')
                    ->canBeEnabled()
                    ->validate()
                        ->ifTrue(static fn ($v) => $v['enabled'])
                        ->thenInvalid('Enabling the doctrine/annotations integration is not supported anymore.')
                ->end()
            ->end()
        ;
    }

    private function addSerializerSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $defaultContextNode = fn () => (new NodeBuilder())
            ->arrayNode('default_context')
                ->normalizeKeys(false)
                ->validate()
                    ->ifTrue(fn () => $this->debug && class_exists(JsonParser::class))
                    ->then(fn (array $v) => $v + [JsonDecode::DETAILED_ERROR_MESSAGES => true])
                ->end()
                ->defaultValue([])
                ->prototype('variable')->end()
        ;

        $rootNode
            ->children()
                ->arrayNode('serializer')
                    ->info('Serializer configuration')
                    ->{$enableIfStandalone('symfony/serializer', Serializer::class)}()
                    ->children()
                        ->booleanNode('enable_attributes')->{class_exists(FullStack::class) ? 'defaultFalse' : 'defaultTrue'}()->end()
                        ->scalarNode('name_converter')->end()
                        ->scalarNode('circular_reference_handler')->end()
                        ->scalarNode('max_depth_handler')->end()
                        ->arrayNode('mapping')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('paths', 'path')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->append($defaultContextNode())
                        ->arrayNode('named_serializers', 'named_serializer')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('name_converter')->end()
                                    ->append($defaultContextNode())
                                    ->booleanNode('include_built_in_normalizers')
                                        ->info('Whether to include the built-in normalizers')
                                        ->defaultTrue()
                                    ->end()
                                    ->booleanNode('include_built_in_encoders')
                                        ->info('Whether to include the built-in encoders')
                                        ->defaultTrue()
                                    ->end()
                                ->end()
                            ->end()
                            ->validate()
                                ->ifTrue(fn ($v) => isset($v['default']))
                                ->thenInvalid('"default" is a reserved name.')
                            ->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(fn ($v) => $this->debug && class_exists(JsonParser::class) && !isset($v['default_context'][JsonDecode::DETAILED_ERROR_MESSAGES]))
                        ->then(function ($v) {
                            $v['default_context'][JsonDecode::DETAILED_ERROR_MESSAGES] = true;

                            return $v;
                        })
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addPropertyAccessSection(ArrayNodeDefinition $rootNode, callable $willBeAvailable): void
    {
        $rootNode
            ->children()
                ->arrayNode('property_access')
                    ->addDefaultsIfNotSet()
                    ->info('Property access configuration')
                    ->{$willBeAvailable('symfony/property-access', PropertyAccessor::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->children()
                        ->booleanNode('magic_call')->defaultFalse()->end()
                        ->booleanNode('magic_get')->defaultTrue()->end()
                        ->booleanNode('magic_set')->defaultTrue()->end()
                        ->booleanNode('throw_exception_on_invalid_index')->defaultFalse()->end()
                        ->booleanNode('throw_exception_on_invalid_property_path')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addPropertyInfoSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('property_info')
                    ->info('Property info configuration')
                    ->{$enableIfStandalone('symfony/property-info', PropertyInfoExtractorInterface::class)}()
                    ->children()
                        ->booleanNode('with_constructor_extractor')
                            ->info('Registers the constructor extractor.')
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(fn ($v) => $v['property_info']['enabled'] && !isset($v['property_info']['with_constructor_extractor']))
                ->then(function ($v) {
                    $v['property_info']['with_constructor_extractor'] = false;

                    trigger_deprecation('symfony/framework-bundle', '7.3', 'Not setting the "property_info.with_constructor_extractor" option explicitly is deprecated because its default value will change in version 8.0.');

                    return $v;
                })
            ->end()
        ;
    }

    private function addTypeInfoSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('type_info')
                    ->info('Type info configuration')
                    ->{$enableIfStandalone('symfony/type-info', Type::class)}()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('aliases', 'alias')
                            ->info('Additional type aliases to be used during type context creation.')
                            ->defaultValue([])
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addCacheSection(ArrayNodeDefinition $rootNode, callable $willBeAvailable): void
    {
        $rootNode
            ->children()
                ->arrayNode('cache')
                    ->info('Cache configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('prefix_seed')
                            ->info('Used to namespace cache keys when using several apps with the same shared backend.')
                            ->defaultValue('_%kernel.project_dir%.%kernel.container_class%')
                            ->example('my-application-name/%kernel.environment%')
                        ->end()
                        ->scalarNode('app')
                            ->info('App related cache pools configuration.')
                            ->defaultValue('cache.adapter.filesystem')
                        ->end()
                        ->scalarNode('system')
                            ->info('System related cache pools configuration.')
                            ->defaultValue('cache.adapter.system')
                        ->end()
                        ->scalarNode('directory')->defaultValue('%kernel.share_dir%/pools/app')->end()
                        ->scalarNode('default_psr6_provider')->end()
                        ->scalarNode('default_redis_provider')->defaultValue('redis://localhost')->end()
                        ->scalarNode('default_valkey_provider')->defaultValue('valkey://localhost')->end()
                        ->scalarNode('default_memcached_provider')->defaultValue('memcached://localhost')->end()
                        ->scalarNode('default_doctrine_dbal_provider')->defaultValue('database_connection')->end()
                        ->scalarNode('default_pdo_provider')->defaultValue($willBeAvailable('doctrine/dbal', Connection::class) && class_exists(DoctrineAdapter::class) ? 'database_connection' : null)->end()
                        ->arrayNode('pools', 'pool')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->validate()
                                    ->ifTrue(static fn ($v) => isset($v['provider']) && 1 < \count($v['adapters']))
                                    ->thenInvalid('Pool cannot have a "provider" while more than one adapter is defined')
                                ->end()
                                ->children()
                                    ->arrayNode('adapters', 'adapter')
                                        ->performNoDeepMerging()
                                        ->info('One or more adapters to chain for creating the pool, defaults to "cache.app".')
                                        ->acceptAndWrap(['string'])
                                        ->beforeNormalization()
                                            ->ifArray()
                                            ->then(static function ($values) {
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
                                    ->scalarNode('default_lifetime')
                                        ->info('Default lifetime of the pool.')
                                        ->example('"300" for 5 minutes expressed in seconds, "PT5M" for five minutes expressed as ISO 8601 time interval, or "5 minutes" as a date expression')
                                    ->end()
                                    ->scalarNode('provider')
                                        ->info('Overwrite the setting from the default provider for this adapter.')
                                    ->end()
                                    ->scalarNode('early_expiration_message_bus')
                                        ->example('"messenger.default_bus" to send early expiration events to the default Messenger bus.')
                                    ->end()
                                    ->scalarNode('clearer')->end()
                                ->end()
                            ->end()
                            ->validate()
                                ->ifTrue(fn ($v) => isset($v['cache.app']) || isset($v['cache.system']))
                                ->thenInvalid('"cache.app" and "cache.system" are reserved names')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addPhpErrorsSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('php_errors')
                    ->info('PHP errors handling configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->variableNode('log')
                            ->info('Use the application logger instead of the PHP logger for logging PHP errors.')
                            ->example('"true" to use the default configuration: log all errors. "false" to disable. An integer bit field of E_* constants, or an array mapping E_* constants to log levels.')
                            ->treatNullLike($this->debug)
                            ->defaultTrue()
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(function (array $v): array {
                                    if (!($v[0]['type'] ?? false)) {
                                        return $v;
                                    }

                                    // Fix XML normalization

                                    $ret = [];
                                    foreach ($v as ['type' => $type, 'logLevel' => $logLevel]) {
                                        $ret[$type] = $logLevel;
                                    }

                                    return $ret;
                                })
                            ->end()
                            ->validate()
                                ->ifTrue(fn ($v) => !(\is_int($v) || \is_bool($v) || \is_array($v)))
                                ->thenInvalid('The "php_errors.log" parameter should be either an integer, a boolean, or an array')
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

    private function addExceptionsSection(ArrayNodeDefinition $rootNode): void
    {
        $logLevels = (new \ReflectionClass(LogLevel::class))->getConstants();

        $rootNode
            ->children()
                ->arrayNode('exceptions', 'exception')
                    ->info('Exception handling configuration')
                    ->useAttributeAsKey('class')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('log_level')
                                ->info('The level of log message. Null to let Symfony decide.')
                                ->validate()
                                    ->ifTrue(fn ($v) => null !== $v && !\in_array($v, $logLevels, true))
                                    ->thenInvalid(\sprintf('The log level is not valid. Pick one among "%s".', implode('", "', $logLevels)))
                                ->end()
                                ->defaultNull()
                            ->end()
                            ->scalarNode('status_code')
                                ->info('The status code of the response. Null or 0 to let Symfony decide.')
                                ->beforeNormalization()
                                    ->ifTrue(fn ($v) => 0 === $v)
                                    ->then(fn ($v) => null)
                                ->end()
                                ->validate()
                                    ->ifTrue(fn ($v) => null !== $v && ($v < 100 || $v > 599))
                                    ->thenInvalid('The status code is not valid. Pick a value between 100 and 599.')
                                ->end()
                                ->defaultNull()
                            ->end()
                            ->scalarNode('log_channel')
                                ->info('The channel of log message. Null to let Symfony decide.')
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addLockSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('lock')
                    ->info('Lock configuration')
                    ->acceptAndWrap(['string'], 'resources')
                    ->{$enableIfStandalone('symfony/lock', Lock::class)}()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($v) {
                            if (!isset($v['resources']) && !isset($v['resource'])) {
                                $v = ['resources' => $v];
                                if (\array_key_exists('enabled', $v['resources'])) {
                                    $v['enabled'] = $v['resources']['enabled'];
                                    unset($v['resources']['enabled']);
                                }
                            }

                            return $v;
                        })
                    ->end()
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn ($v) => $v['enabled'] && !$v['resources'])
                        ->thenInvalid('At least one resource must be defined.')
                    ->end()
                    ->children()
                        ->arrayNode('resources', 'resource')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->defaultValue(['default' => [class_exists(SemaphoreStore::class) && SemaphoreStore::isSupported() ? 'semaphore' : 'flock']])
                            ->acceptAndWrap(['string'], 'default')
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(static function ($v) {
                                    if (!array_is_list($v)) {
                                        return $v;
                                    }

                                    $resources = [];
                                    foreach ($v as $resource) {
                                        $resources[] = \is_array($resource) && isset($resource['name'])
                                            ? [$resource['name'] => $resource['value']]
                                            : ['default' => $resource]
                                        ;
                                    }

                                    return array_merge_recursive([], ...$resources);
                                })
                            ->end()
                            ->prototype('array')
                                ->performNoDeepMerging()
                                ->acceptAndWrap(['string'])
                                // acceptAndWrap() doesn't list null as an accepted value on purpose,
                                // yet the XML loader can yield some and we should convert them to 'null'
                                ->beforeNormalization()->ifNull()->then(static fn () => ['null'])->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addSemaphoreSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('semaphore')
                    ->info('Semaphore configuration')
                    ->acceptAndWrap(['string'], 'resources')
                    ->{$enableIfStandalone('symfony/semaphore', Semaphore::class)}()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($v) {
                            if (!isset($v['resources']) && !isset($v['resource'])) {
                                $v = ['resources' => $v];
                                if (\array_key_exists('enabled', $v['resources'])) {
                                    $v['enabled'] = $v['resources']['enabled'];
                                    unset($v['resources']['enabled']);
                                }
                            }

                            return $v;
                        })
                    ->end()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('resources', 'resource')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->requiresAtLeastOneElement()
                            ->acceptAndWrap(['string'], 'default')
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(static function ($v) {
                                    if (!array_is_list($v)) {
                                        return $v;
                                    }

                                    $resources = [];
                                    foreach ($v as $resource) {
                                        $resources[] = \is_array($resource) && isset($resource['name'])
                                            ? [$resource['name'] => $resource['value']]
                                            : ['default' => $resource]
                                        ;
                                    }

                                    return array_merge_recursive([], ...$resources);
                                })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addWebLinkSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('web_link')
                    ->info('Web links configuration')
                    ->{$enableIfStandalone('symfony/weblink', HttpHeaderSerializer::class)}()
                ->end()
            ->end()
        ;
    }

    private function addMessengerSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('messenger')
                    ->info('Messenger configuration')
                    ->{$enableIfStandalone('symfony/messenger', MessageBusInterface::class)}()
                    ->validate()
                        ->ifTrue(fn ($v) => isset($v['buses']) && \count($v['buses']) > 1 && null === $v['default_bus'])
                        ->thenInvalid('You must specify the "default_bus" if you define more than one bus.')
                    ->end()
                    ->validate()
                        ->ifTrue(fn ($v) => isset($v['buses']) && null !== $v['default_bus'] && !isset($v['buses'][$v['default_bus']]))
                        ->then(fn ($v) => throw new InvalidConfigurationException(\sprintf('The specified default bus "%s" is not configured. Available buses are "%s".', $v['default_bus'], implode('", "', array_keys($v['buses'])))))
                    ->end()
                    ->children()
                        ->arrayNode('routing')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('message_class')
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(function ($config) {
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
                        ->arrayNode('transports', 'transport')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->acceptAndWrap(['string'], 'dsn')
                                ->children()
                                    ->scalarNode('dsn')->end()
                                    ->scalarNode('serializer')->defaultNull()->info('Service id of a custom serializer to use.')->end()
                                    ->arrayNode('options', 'option')
                                        ->normalizeKeys(false)
                                        ->defaultValue([])
                                        ->prototype('variable')
                                        ->end()
                                    ->end()
                                    ->scalarNode('failure_transport')
                                        ->defaultNull()
                                        ->info('Transport name to send failed messages to (after all retries have failed).')
                                    ->end()
                                    ->arrayNode('retry_strategy')
                                        ->addDefaultsIfNotSet()
                                        ->acceptAndWrap(['string'], 'service')
                                        ->beforeNormalization()
                                            ->ifArray()
                                            ->then(static function ($v) {
                                                if (isset($v['service']) && (isset($v['max_retries']) || isset($v['delay']) || isset($v['multiplier']) || isset($v['max_delay']))) {
                                                    throw new \InvalidArgumentException('The "service" cannot be used along with the other "retry_strategy" options.');
                                                }

                                                return $v;
                                            })
                                        ->end()
                                        ->children()
                                            ->scalarNode('service')->defaultNull()->info('Service id to override the retry strategy entirely.')->end()
                                            ->integerNode('max_retries')->defaultValue(3)->min(0)->end()
                                            ->integerNode('delay')->defaultValue(1000)->min(0)->info('Time in ms to delay (or the initial value when multiplier is used).')->end()
                                            ->floatNode('multiplier')->defaultValue(2)->min(1)->info('If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries)).')->end()
                                            ->integerNode('max_delay')->defaultValue(0)->min(0)->info('Max time in ms that a retry should ever be delayed (0 = infinite).')->end()
                                            ->floatNode('jitter')->defaultValue(0.1)->min(0)->max(1)->info('Randomness to apply to the delay (between 0 and 1).')->end()
                                        ->end()
                                    ->end()
                                    ->scalarNode('rate_limiter')
                                        ->defaultNull()
                                        ->info('Rate limiter name to use when processing messages.')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('failure_transport')
                            ->defaultNull()
                            ->info('Transport name to send failed messages to (after all retries have failed).')
                        ->end()
                        ->arrayNode('stop_worker_on_signals', 'stop_worker_on_signal')
                            ->defaultValue([])
                            ->info('A list of signals that should stop the worker; defaults to SIGTERM and SIGINT.')
                            ->acceptAndWrap(['int', 'string'])
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(static function ($signals) {
                                    return array_map(static function ($v) {
                                        if (\is_string($v) && str_starts_with($v, 'SIG') && \array_key_exists($v, get_defined_constants(true)['pcntl'])) {
                                            return \constant($v);
                                        }

                                        if (!\is_int($v)) {
                                            throw new InvalidConfigurationException('The "stop_worker_on_signals" option must be an array of pcntl signals in messenger configuration.');
                                        }

                                        return $v;
                                    }, $signals);
                                })
                            ->end()
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('default_bus')->defaultNull()->end()
                        ->arrayNode('buses', 'bus')
                            ->defaultValue(['messenger.bus.default' => ['default_middleware' => ['enabled' => true, 'allow_no_handlers' => false, 'allow_no_senders' => true], 'middleware' => []]])
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('default_middleware')
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(static fn ($v) => [
                                                'enabled' => 'allow_no_handlers' === $v,
                                                'allow_no_handlers' => 'allow_no_handlers' === $v,
                                            ])
                                        ->end()
                                        ->beforeNormalization()->ifTrue()->then(static fn () => ['enabled' => true])->end()
                                        ->beforeNormalization()->ifFalse()->then(static fn () => ['enabled' => false])->end()
                                        ->canBeDisabled()
                                        ->children()
                                            ->booleanNode('allow_no_handlers')->defaultFalse()->end()
                                            ->booleanNode('allow_no_senders')->defaultTrue()->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('middleware')
                                        ->performNoDeepMerging()
                                        ->acceptAndWrap(['string'])
                                        ->beforeNormalization()
                                            ->ifArray()
                                            ->then(static fn ($v) => \is_string(key($v)) ? [$v] : $v)
                                        ->end()
                                        ->defaultValue([])
                                        ->arrayPrototype()
                                            ->acceptAndWrap(['string'], 'id')
                                            ->beforeNormalization()
                                                ->ifArray()
                                                ->then(static function ($middleware): array {
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
                                            ->children()
                                                ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                                                ->arrayNode('arguments', 'argument')
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

    private function addSchedulerSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('scheduler')
                    ->info('Scheduler configuration')
                    ->{$enableIfStandalone('symfony/scheduler', Schedule::class)}()
                ->end()
            ->end()
        ;
    }

    private function addRobotsIndexSection(ArrayNodeDefinition $rootNode): void
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

    private function addHttpClientSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('http_client')
                    ->info('HTTP Client configuration')
                    ->{$enableIfStandalone('symfony/http-client', HttpClient::class)}()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($config) {
                            if (!($config['scoped_clients'] ?? false)) {
                                return $config;
                            }

                            $hasDefaultRateLimiter = isset($config['default_options']['rate_limiter']);
                            $hasDefaultRetryFailed = \is_array($config['default_options']['retry_failed'] ?? null);

                            if (!$hasDefaultRateLimiter && !$hasDefaultRetryFailed) {
                                return $config;
                            }

                            foreach ($config['scoped_clients'] as &$scopedConfig) {
                                if ($hasDefaultRateLimiter) {
                                    if (!isset($scopedConfig['rate_limiter']) || true === $scopedConfig['rate_limiter']) {
                                        $scopedConfig['rate_limiter'] = $config['default_options']['rate_limiter'];
                                    } elseif (false === $scopedConfig['rate_limiter']) {
                                        $scopedConfig['rate_limiter'] = null;
                                    }
                                }

                                if ($hasDefaultRetryFailed) {
                                    if (!isset($scopedConfig['retry_failed']) || true === $scopedConfig['retry_failed']) {
                                        $scopedConfig['retry_failed'] = $config['default_options']['retry_failed'];
                                    } elseif (\is_array($scopedConfig['retry_failed'])) {
                                        $scopedConfig['retry_failed'] += $config['default_options']['retry_failed'];
                                    }
                                }
                            }

                            return $config;
                        })
                    ->end()
                    ->children()
                        ->integerNode('max_host_connections')
                            ->info('The maximum number of connections to a single host.')
                        ->end()
                        ->arrayNode('default_options')
                            ->children()
                                ->arrayNode('headers', 'header')
                                    ->info('Associative array: header => value(s).')
                                    ->useAttributeAsKey('name')
                                    ->normalizeKeys(false)
                                    ->variablePrototype()->end()
                                ->end()
                                ->arrayNode('vars', 'var')
                                    ->info('Associative array: the default vars used to expand the templated URI.')
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
                                        ->ifArray()
                                        ->then(static function ($config) {
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
                                    ->info('Indicates if the peer should be verified in a TLS context.')
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
                                    ->info('A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)')
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
                                ->scalarNode('crypto_method')
                                    ->info('The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.')
                                ->end()
                                ->arrayNode('extra')
                                    ->info('Extra options for specific HTTP client.')
                                    ->normalizeKeys(false)
                                    ->variablePrototype()->end()
                                ->end()
                                ->scalarNode('rate_limiter')
                                    ->defaultNull()
                                    ->info('Rate limiter name to use for throttling requests.')
                                ->end()
                                ->append($this->createHttpClientCachingSection())
                                ->append($this->createHttpClientRetrySection())
                            ->end()
                        ->end()
                        ->scalarNode('mock_response_factory')
                            ->info('The id of the service that should generate mock responses. It should be either an invokable or an iterable.')
                        ->end()
                        ->arrayNode('scoped_clients', 'scoped_client')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->acceptAndWrap(['string'], 'base_uri')
                                ->validate()
                                    ->ifTrue(static fn () => !class_exists(HttpClient::class))
                                    ->then(static fn () => throw new LogicException('HttpClient support cannot be enabled as the component is not installed. Try running "composer require symfony/http-client".'))
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn ($v) => !isset($v['scope']) && !isset($v['base_uri']))
                                    ->thenInvalid('Either "scope" or "base_uri" should be defined.')
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn ($v) => !empty($v['query']) && !isset($v['base_uri']))
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
                                            ->ifArray()
                                            ->then(static function ($config) {
                                                if (!isset($config['key'], $config['value']) || \count($config) > 2) {
                                                    return $config;
                                                }

                                                return [$config['key'] => $config['value']];
                                            })
                                        ->end()
                                        ->normalizeKeys(false)
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->arrayNode('headers', 'header')
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
                                            ->ifArray()
                                            ->then(static function ($config) {
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
                                        ->info('Indicates if the peer should be verified in a TLS context.')
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
                                        ->info('A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).')
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
                                    ->scalarNode('crypto_method')
                                        ->info('The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.')
                                    ->end()
                                    ->arrayNode('extra')
                                        ->info('Extra options for specific HTTP client.')
                                        ->normalizeKeys(false)
                                        ->variablePrototype()->end()
                                    ->end()
                                    ->scalarNode('rate_limiter')
                                        ->defaultNull()
                                        ->info('Rate limiter name to use for throttling requests.')
                                    ->end()
                                    ->append($this->createHttpClientCachingSection())
                                    ->append($this->createHttpClientRetrySection())
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function createHttpClientCachingSection(): ArrayNodeDefinition
    {
        $root = new NodeBuilder();

        return $root
            ->arrayNode('caching')
                ->info('Caching configuration.')
                ->canBeEnabled()
                ->addDefaultsIfNotSet()
                ->children()
                    ->stringNode('cache_pool')
                        ->info('The taggable cache pool to use for storing the responses.')
                        ->defaultValue('cache.http_client')
                        ->cannotBeEmpty()
                    ->end()
                    ->booleanNode('shared')
                        ->info('Indicates whether the cache is shared (public) or private.')
                        ->defaultTrue()
                    ->end()
                    ->integerNode('max_ttl')
                        ->info('The maximum TTL (in seconds) allowed for cached responses. Null means no cap.')
                        ->defaultNull()
                        ->min(0)
                    ->end()
                ->end();
    }

    private function createHttpClientRetrySection(): ArrayNodeDefinition
    {
        $root = new NodeBuilder();

        return $root
            ->arrayNode('retry_failed')
                ->canBeEnabled()
                ->addDefaultsIfNotSet()
                ->beforeNormalization()
                    ->ifArray()
                    ->then(static function ($v) {
                        if (isset($v['retry_strategy']) && (isset($v['http_codes']) || isset($v['delay']) || isset($v['multiplier']) || isset($v['max_delay']) || isset($v['jitter']))) {
                            throw new \InvalidArgumentException('The "retry_strategy" option cannot be used along with the "http_codes", "delay", "multiplier", "max_delay" or "jitter" options.');
                        }

                        return $v;
                    })
                ->end()
                ->children()
                    ->scalarNode('retry_strategy')->defaultNull()->info('service id to override the retry strategy.')->end()
                    ->arrayNode('http_codes', 'http_code')
                        ->performNoDeepMerging()
                        ->acceptAndWrap(['int', 'string'])
                        ->beforeNormalization()
                            ->ifArray()
                            ->then(static function ($v) {
                                $list = [];
                                foreach ($v as $key => $val) {
                                    if (is_numeric($val)) {
                                        $list[] = ['code' => $val];
                                    } elseif (\is_array($val)) {
                                        if (isset($val['code']) || isset($val['methods'])) {
                                            $list[] = $val;
                                        } else {
                                            $list[] = ['code' => $key, 'methods' => $val];
                                        }
                                    } elseif (true === $val || null === $val) {
                                        $list[] = ['code' => $key];
                                    }
                                }

                                return $list;
                            })
                        ->end()
                        ->useAttributeAsKey('code')
                        ->arrayPrototype()
                            ->children()
                                ->integerNode('code')->end()
                                ->arrayNode('methods', 'method')
                                    ->acceptAndWrap(['string'])
                                    ->beforeNormalization()
                                    ->ifArray()
                                        ->then(static fn ($v) => array_map('strtoupper', $v))
                                    ->end()
                                    ->stringPrototype()->end()
                                    ->info('A list of HTTP methods that triggers a retry for this status code. When empty, all methods are retried.')
                                ->end()
                            ->end()
                        ->end()
                        ->info('A list of HTTP status code that triggers a retry.')
                    ->end()
                    ->integerNode('max_retries')->defaultValue(3)->min(0)->end()
                    ->integerNode('delay')->defaultValue(1000)->min(0)->info('Time in ms to delay (or the initial value when multiplier is used).')->end()
                    ->floatNode('multiplier')->defaultValue(2)->min(1)->info('If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries).')->end()
                    ->integerNode('max_delay')->defaultValue(0)->min(0)->info('Max time in ms that a retry should ever be delayed (0 = infinite).')->end()
                    ->floatNode('jitter')->defaultValue(0.1)->min(0)->max(1)->info('Randomness in percent (between 0 and 1) to apply to the delay.')->end()
                ->end()
        ;
    }

    private function addMailerSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('mailer')
                    ->info('Mailer configuration')
                    ->{$enableIfStandalone('symfony/mailer', Mailer::class)}()
                    ->validate()
                        ->ifTrue(fn ($v) => isset($v['dsn']) && \count($v['transports']))
                        ->thenInvalid('"dsn" and "transports" cannot be used together.')
                    ->end()
                    ->children()
                        ->scalarNode('message_bus')->defaultNull()->info('The message bus to use. Defaults to the default bus if the Messenger component is installed.')->end()
                        ->scalarNode('dsn')->defaultNull()->end()
                        ->arrayNode('transports', 'transport')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('envelope')
                            ->info('Mailer Envelope configuration')
                            ->children()
                                ->scalarNode('sender')->end()
                                ->arrayNode('recipients', 'recipient')
                                    ->performNoDeepMerging()
                                    ->acceptAndWrap(['string'])
                                    ->beforeNormalization()
                                        ->ifArray()
                                        ->then(static fn ($v) => array_values(array_filter($v)))
                                    ->end()
                                    ->prototype('scalar')->end()
                                ->end()
                                ->arrayNode('allowed_recipients', 'allowed_recipient')
                                    ->info('A list of regular expressions that allow recipients when "recipients" option is defined.')
                                    ->example(['.*@example\.com'])
                                    ->performNoDeepMerging()
                                    ->acceptAndWrap(['string'])
                                    ->beforeNormalization()
                                        ->ifArray()
                                        ->then(static fn ($v) => array_values(array_filter($v)))
                                    ->end()
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('headers', 'header')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->normalizeKeys(false)
                                ->acceptAndWrap(['string'], 'value')
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static fn ($v) => array_keys($v) !== ['value'] ? ['value' => $v] : $v)
                                ->end()
                                ->children()
                                    ->variableNode('value')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('dkim_signer')
                            ->addDefaultsIfNotSet()
                            ->canBeEnabled()
                            ->info('DKIM signer configuration')
                            ->children()
                                ->scalarNode('key')
                                    ->info('Key content, or path to key (in PEM format with the `file://` prefix)')
                                    ->defaultValue('')
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('domain')->defaultValue('')->end()
                                ->scalarNode('select')->defaultValue('')->end()
                                ->scalarNode('passphrase')
                                    ->info('The private key passphrase')
                                    ->defaultValue('')
                                ->end()
                                ->arrayNode('options', 'option')
                                    ->performNoDeepMerging()
                                    ->normalizeKeys(false)
                                    ->useAttributeAsKey('name')
                                    ->prototype('variable')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('smime_signer')
                            ->addDefaultsIfNotSet()
                            ->canBeEnabled()
                            ->info('S/MIME signer configuration')
                            ->children()
                                ->scalarNode('key')
                                    ->info('Path to key (in PEM format)')
                                    ->defaultValue('')
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('certificate')
                                    ->info('Path to certificate (in PEM format without the `file://` prefix)')
                                    ->defaultValue('')
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('passphrase')
                                    ->info('The private key passphrase')
                                    ->defaultNull()
                                ->end()
                                ->scalarNode('extra_certificates')->defaultNull()->end()
                                ->integerNode('sign_options')->defaultNull()->end()
                            ->end()
                        ->end()
                        ->arrayNode('smime_encrypter')
                            ->addDefaultsIfNotSet()
                            ->canBeEnabled()
                            ->info('S/MIME encrypter configuration')
                            ->children()
                                ->scalarNode('repository')
                                    ->info('S/MIME certificate repository service. This service shall implement the `Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface`.')
                                    ->defaultValue('')
                                    ->cannotBeEmpty()
                                ->end()
                                ->integerNode('cipher')
                                    ->info('A set of algorithms used to encrypt the message')
                                    ->defaultNull()
                                    ->beforeNormalization()
                                        ->ifString()
                                        ->then(static function ($v): ?int {
                                            if (\defined('OPENSSL_CIPHER_'.$v)) {
                                                return \constant('OPENSSL_CIPHER_'.$v);
                                            }

                                            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid OPENSSL cipher.', $v));
                                        })
                                    ->end()
                                    ->validate()
                                        ->ifTrue(function ($v) {
                                            return \extension_loaded('openssl') && null !== $v && !\defined('OPENSSL_CIPHER_'.$v);
                                        })
                                        ->thenInvalid('You must provide a valid cipher.')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addNotifierSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('notifier')
                    ->info('Notifier configuration')
                    ->{$enableIfStandalone('symfony/notifier', Notifier::class)}()
                    ->children()
                        ->scalarNode('message_bus')->defaultNull()->info('The message bus to use. Defaults to the default bus if the Messenger component is installed.')->end()
                        ->arrayNode('chatter_transports', 'chatter_transport')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('texter_transports', 'texter_transport')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                        ->booleanNode('notification_on_failed_messages')->defaultFalse()->end()
                        ->arrayNode('channel_policy')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->acceptAndWrap(['string'])
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                        ->arrayNode('admin_recipients', 'admin_recipient')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('email')->cannotBeEmpty()->end()
                                    ->scalarNode('phone')->defaultValue('')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addWebhookSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('webhook')
                    ->info('Webhook configuration')
                    ->{$enableIfStandalone('symfony/webhook', WebhookController::class)}()
                    ->children()
                        ->scalarNode('message_bus')->defaultValue('messenger.default_bus')->info('The message bus to use.')->end()
                        ->arrayNode('routing')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('type')
                            ->prototype('array')
                                ->children()
                                ->scalarNode('service')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('secret')
                                    ->defaultValue('')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addRemoteEventSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('remote-event')
                    ->info('RemoteEvent configuration')
                    ->{$enableIfStandalone('symfony/remote-event', RemoteEvent::class)}()
                ->end()
            ->end()
        ;
    }

    private function addRateLimiterSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('rate_limiter')
                    ->info('Rate limiter configuration')
                    ->{$enableIfStandalone('symfony/rate-limiter', TokenBucketLimiter::class)}()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($v) {
                            if (!isset($v['limiters']) && !isset($v['limiter'])) {
                                $v = ['limiters' => $v];
                                if (\array_key_exists('enabled', $v['limiters'])) {
                                    $v['enabled'] = $v['limiters']['enabled'];
                                    unset($v['limiters']['enabled']);
                                }
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->arrayNode('limiters', 'limiter')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('lock_factory')
                                        ->info('The service ID of the lock factory used by this limiter (or null to disable locking).')
                                        ->defaultValue('auto')
                                    ->end()
                                    ->scalarNode('cache_pool')
                                        ->info('The cache pool to use for storing the current limiter state.')
                                        ->defaultValue('cache.rate_limiter')
                                    ->end()
                                    ->scalarNode('storage_service')
                                        ->info('The service ID of a custom storage implementation, this precedes any configured "cache_pool".')
                                        ->defaultNull()
                                    ->end()
                                    ->enumNode('policy')
                                        ->info('The algorithm to be used by this limiter.')
                                        ->isRequired()
                                        ->values(['fixed_window', 'token_bucket', 'sliding_window', 'compound', 'no_limit'])
                                    ->end()
                                    ->arrayNode('limiters', 'limiter')
                                        ->info('The limiter names to use when using the "compound" policy.')
                                        ->acceptAndWrap(['string'])
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->integerNode('limit')
                                        ->info('The maximum allowed hits in a fixed interval or burst.')
                                    ->end()
                                    ->scalarNode('interval')
                                        ->info('Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')
                                    ->end()
                                    ->arrayNode('rate')
                                        ->info('Configures the fill rate if "policy" is set to "token_bucket".')
                                        ->children()
                                            ->scalarNode('interval')
                                                ->info('Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')
                                            ->end()
                                            ->integerNode('amount')->info('Amount of tokens to add each interval.')->defaultValue(1)->end()
                                        ->end()
                                    ->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn ($v) => !\in_array($v['policy'], ['no_limit', 'compound'], true) && !isset($v['limit']))
                                    ->thenInvalid('A limit must be provided when using a policy different than "compound" or "no_limit".')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addUidSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('uid')
                    ->info('Uid configuration')
                    ->{$enableIfStandalone('symfony/uid', UuidFactory::class)}()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('default_uuid_version')
                            ->values([7, 6, 4, 1])
                            ->defaultValue(7)
                        ->end()
                        ->enumNode('name_based_uuid_version')
                            ->defaultValue(5)
                            ->values([5, 3])
                        ->end()
                        ->scalarNode('name_based_uuid_namespace')
                            ->cannotBeEmpty()
                        ->end()
                        ->enumNode('time_based_uuid_version')
                            ->values([7, 6, 1])
                            ->defaultValue(7)
                        ->end()
                        ->scalarNode('time_based_uuid_node')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addHtmlSanitizerSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('html_sanitizer')
                    ->info('HtmlSanitizer configuration')
                    ->{$enableIfStandalone('symfony/html-sanitizer', HtmlSanitizerInterface::class)}()
                    ->children()
                        ->arrayNode('sanitizers', 'sanitizer')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->booleanNode('allow_safe_elements')
                                        ->info('Allows "safe" elements and attributes.')
                                        ->defaultFalse()
                                    ->end()
                                    ->booleanNode('allow_static_elements')
                                        ->info('Allows all static elements and attributes from the W3C Sanitizer API standard.')
                                        ->defaultFalse()
                                    ->end()
                                    ->arrayNode('allow_elements', 'allow_element')
                                        ->info('Configures the elements that the sanitizer should retain from the input. The element name is the key, the value is either a list of allowed attributes for this element or "*" to allow the default set of attributes (https://wicg.github.io/sanitizer-api/#default-configuration).')
                                        ->example(['i' => '*', 'a' => ['title'], 'span' => 'class'])
                                        ->normalizeKeys(false)
                                        ->useAttributeAsKey('name')
                                        ->variablePrototype()
                                            ->beforeNormalization()
                                                ->ifArray()->then(static fn ($n) => $n['attribute'] ?? $n)
                                            ->end()
                                            ->validate()
                                                ->ifTrue(fn ($n): bool => !\is_string($n) && !\is_array($n))
                                                ->thenInvalid('The value must be either a string or an array of strings.')
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('block_elements', 'block_element')
                                        ->info('Configures elements as blocked. Blocked elements are elements the sanitizer should remove from the input, but retain their children.')
                                        ->acceptAndWrap(['string'])
                                        ->stringPrototype()->end()
                                    ->end()
                                    ->arrayNode('drop_elements', 'drop_element')
                                        ->info('Configures elements as dropped. Dropped elements are elements the sanitizer should remove from the input, including their children.')
                                        ->acceptAndWrap(['string'])
                                        ->stringPrototype()->end()
                                    ->end()
                                    ->arrayNode('allow_attributes', 'allow_attribute')
                                        ->info('Configures attributes as allowed. Allowed attributes are attributes the sanitizer should retain from the input.')
                                        ->normalizeKeys(false)
                                        ->useAttributeAsKey('name')
                                        ->variablePrototype()
                                            ->beforeNormalization()
                                                ->ifArray()->then(static fn ($n) => $n['element'] ?? $n)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('drop_attributes', 'drop_attribute')
                                        ->info('Configures attributes as dropped. Dropped attributes are attributes the sanitizer should remove from the input.')
                                        ->normalizeKeys(false)
                                        ->useAttributeAsKey('name')
                                        ->variablePrototype()
                                            ->beforeNormalization()
                                                ->ifArray()->then(static fn ($n) => $n['element'] ?? $n)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('force_attributes', 'force_attribute')
                                        ->info('Forcefully set the values of certain attributes on certain elements.')
                                        ->normalizeKeys(false)
                                        ->useAttributeAsKey('name')
                                        ->arrayPrototype()
                                            ->normalizeKeys(false)
                                            ->useAttributeAsKey('name')
                                            ->stringPrototype()->end()
                                        ->end()
                                    ->end()
                                    ->booleanNode('force_https_urls')
                                        ->info('Transforms URLs using the HTTP scheme to use the HTTPS scheme instead.')
                                        ->defaultFalse()
                                    ->end()
                                    ->arrayNode('allowed_link_schemes', 'allowed_link_scheme')
                                        ->info('Allows only a given list of schemes to be used in links href attributes.')
                                        ->acceptAndWrap(['string'])
                                        ->stringPrototype()->end()
                                    ->end()
                                    ->arrayNode('allowed_link_hosts', 'allowed_link_host')
                                        ->info('Allows only a given list of hosts to be used in links href attributes.')
                                        ->defaultNull()
                                        ->acceptAndWrap(['string'])
                                        ->stringPrototype()->end()
                                    ->end()
                                    ->booleanNode('allow_relative_links')
                                        ->info('Allows relative URLs to be used in links href attributes.')
                                        ->defaultFalse()
                                    ->end()
                                    ->arrayNode('allowed_media_schemes', 'allowed_media_scheme')
                                        ->info('Allows only a given list of schemes to be used in media source attributes (img, audio, video, ...).')
                                        ->acceptAndWrap(['string'])
                                        ->stringPrototype()->end()
                                    ->end()
                                    ->arrayNode('allowed_media_hosts', 'allowed_media_host')
                                        ->info('Allows only a given list of hosts to be used in media source attributes (img, audio, video, ...).')
                                        ->defaultNull()
                                        ->acceptAndWrap(['string'])
                                        ->stringPrototype()->end()
                                    ->end()
                                    ->booleanNode('allow_relative_medias')
                                        ->info('Allows relative URLs to be used in media source attributes (img, audio, video, ...).')
                                        ->defaultFalse()
                                    ->end()
                                    ->arrayNode('with_attribute_sanitizers', 'with_attribute_sanitizer')
                                        ->info('Registers custom attribute sanitizers.')
                                        ->acceptAndWrap(['string'])
                                        ->stringPrototype()->end()
                                    ->end()
                                    ->arrayNode('without_attribute_sanitizers', 'without_attribute_sanitizer')
                                        ->info('Unregisters custom attribute sanitizers.')
                                        ->acceptAndWrap(['string'])
                                        ->stringPrototype()->end()
                                    ->end()
                                    ->integerNode('max_input_length')
                                        ->info('The maximum length allowed for the sanitized input.')
                                        ->defaultValue(0)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addJsonStreamerSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone): void
    {
        $rootNode
            ->children()
                ->arrayNode('json_streamer')
                    ->info('JSON streamer configuration')
                    ->{$enableIfStandalone('symfony/json-streamer', StreamWriterInterface::class)}()
                ->end()
            ->end()
        ;
    }
}
