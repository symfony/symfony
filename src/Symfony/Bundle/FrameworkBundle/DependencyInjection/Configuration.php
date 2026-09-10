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

use Psr\Log\LogLevel;
use Symfony\Bundle\FullStack;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\IpUtils;

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
                ->scalarNode('ide')
                    ->defaultValue($this->debug ? '%env(default::SYMFONY_IDE)%' : null)
                    ->setDeprecated('symfony/framework-bundle', '8.2', 'Setting the "%path%.%node%" configuration option is deprecated, use the "SYMFONY_IDE" env var instead.')
                ->end()
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
        $this->addUriSignerSection($rootNode);
        $this->addProfilerSection($rootNode);
        $this->addExtensionAliasSections($rootNode);
        $this->addSessionSection($rootNode);
        $this->addRequestSection($rootNode);
        $this->addPhpErrorsSection($rootNode);
        $this->addExceptionsSection($rootNode);
        $this->addRobotsIndexSection($rootNode);
        $this->addSecretsSection($rootNode);

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
                        ->scalarNode('local_dotenv_file')->defaultValue('%kernel.project_dir%/.env.%kernel.environment%.local')->end()
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

    /**
     * @param-immediately-invoked-callable $enableIfStandalone
     */
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
                        ->scalarNode('cache_status')
                            ->info('Enables the RFC 9211 "Cache-Status" response header and names this cache in it, e.g. "Symfony". No header is added when null.')
                        ->end()
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
                        ->booleanNode('terminate_on_cache_hit')
                            ->setDeprecated('symfony/framework-bundle', '8.1', 'Setting the "%path%.%node%" configuration option is deprecated. It will be removed in version 9.0.')
                        ->end()
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
                        ->scalarNode('hinclude_default_template')
                            ->defaultNull()
                            ->setDeprecated('symfony/framework-bundle', '8.2', 'Setting the "%path%.%node%" configuration option is deprecated. It will be removed in version 9.0.')
                        ->end()
                        ->scalarNode('path')->defaultValue('/_fragment')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addUriSignerSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('uri_signer')
                    ->info('URI signer configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('expiration')
                            ->info('Default expiration of signed URIs, in seconds.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Declares the keys whose value belongs to the configuration of a component's own bundle.
     *
     * MergeExtensionConfigurationPass forwards each of them to the extension named by aliasOf().
     */
    private function addExtensionAliasSections(ArrayNodeDefinition $rootNode): void
    {
        $enabled = ['enabled' => true];
        $children = $rootNode->children();

        // key => [aliased extension, what "true" stands for or null when there is no enabled flag,
        //         accepts a bare string of resources]
        foreach ([
            'workflows' => ['workflow', $enabled, false],
            'router' => ['router', $enabled, false],
            'assets' => ['asset', $enabled, false],
            'asset_mapper' => ['asset_mapper', $enabled, false],
            'translator' => ['translation', $enabled, false],
            'validation' => ['validation', $enabled, false],
            'serializer' => ['serializer', $enabled, false],
            'property_access' => ['property_access', $enabled, false],
            'type_info' => ['type_info', $enabled, false],
            'property_info' => ['property_info', $enabled, false],
            'cache' => ['cache', null, false],
            'web_link' => ['web_link', $enabled, false],
            'lock' => ['lock', [], true],
            'semaphore' => ['semaphore', $enabled, true],
            'messenger' => ['messenger', [], false],
            'scheduler' => ['scheduler', $enabled, false],
            'http_client' => ['http_client', $enabled, false],
            'mailer' => ['mailer', $enabled, false],
            'notifier' => ['notifier', $enabled, false],
            'rate_limiter' => ['rate_limiter', $enabled, false],
            'uid' => ['uid', $enabled, false],
            'html_sanitizer' => ['html_sanitizer', $enabled, false],
            'webhook' => ['webhook', $enabled, false],
            'remote_event' => ['remote_event', $enabled, false],
            'json_streamer' => ['json_streamer', $enabled, false],
        ] as $key => [$alias, $treatTrueLike, $acceptsResourcesAsString]) {
            $node = $children->variableNode($key)->aliasOf($alias);

            if (null !== $treatTrueLike) {
                $node->treatFalseLike(['enabled' => false])->treatTrueLike($treatTrueLike);
            }

            if ($acceptsResourcesAsString) {
                $node->beforeNormalization()->ifString()->then(static fn ($v) => ['resources' => $v])->end();
            }

            $node->end();
        }
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
                        ->arrayNode('excluded_paths', 'excluded_path')
                            ->info('Regular expressions matched against the url-decoded path info of the requests that must not be profiled. Patterns are case-sensitive and must not contain delimiters.')
                            ->example(['^/\.well-known/', '^/favicon\.ico$'])
                            ->acceptAndWrap(['string'])
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->validate()
                                ->ifTrue(static fn ($v) => $v && false === @preg_match('{('.implode('|', $v).')}', ''))
                                ->thenInvalid('Invalid regular expression in the "excluded_paths" option: %s.')
                            ->end()
                        ->end()
                        ->arrayNode('excluded_http_codes', 'excluded_http_code')
                            ->info('Maps HTTP status codes whose responses must not be profiled to regular expressions restricting each exclusion to matching path infos.')
                            ->example([404 => null, 400 => ['^/foo', '^/bar']])
                            ->performNoDeepMerging()
                            ->acceptAndWrap(['int', 'string'])
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(static function ($v) {
                                    $map = [];
                                    foreach ($v as $key => $val) {
                                        if (\is_int($val) || \is_string($val) && is_numeric($val)) {
                                            $map[$val] = [];
                                        } elseif (null === $val || true === $val) {
                                            $map[$key] = [];
                                        } elseif (false !== $val) {
                                            $map[$key] = $val;
                                        }
                                    }

                                    return $map;
                                })
                            ->end()
                            ->validate()
                                ->always(static function ($v) {
                                    foreach (array_keys($v) as $code) {
                                        if (!\is_int($code) || 100 > $code || 599 < $code) {
                                            throw new InvalidConfigurationException(\sprintf('The "excluded_http_codes" option only accepts HTTP status codes between 100 and 599, "%s" given.', $code));
                                        }
                                    }

                                    return $v;
                                })
                            ->end()
                            ->arrayPrototype()
                                ->info('Regular expressions matched against the url-decoded path info that restrict the exclusion of this status code. When empty, every response having that status code is excluded.')
                                ->acceptAndWrap(['string'])
                                ->scalarPrototype()->cannotBeEmpty()->end()
                                ->validate()
                                    ->ifTrue(static fn ($v) => $v && false === @preg_match('{('.implode('|', $v).')}', ''))
                                    ->thenInvalid('Invalid regular expression in the "excluded_http_codes" option: %s.')
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('dsn')->defaultValue('file:%kernel.cache_dir%/profiler')->end()
                        ->enumNode('collect_serializer_data')
                            ->values([true])
                            ->defaultTrue()
                            ->setDeprecated('symfony/framework-bundle', '8.1', 'Setting the "%path%.%node%" configuration option is deprecated. It will be removed in version 9.0.')
                        ->end()
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
                                ->ifTrue(static function ($v) {
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
                                ->then(static function (array $v): array {
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
                                ->ifTrue(static fn ($v) => !(\is_int($v) || \is_bool($v) || \is_array($v)))
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
                                    ->ifTrue(static fn ($v) => null !== $v && !\in_array($v, $logLevels, true))
                                    ->thenInvalid(\sprintf('The log level is not valid. Pick one among "%s".', implode('", "', $logLevels)))
                                ->end()
                                ->defaultNull()
                            ->end()
                            ->scalarNode('status_code')
                                ->info('The status code of the response. Null or 0 to let Symfony decide.')
                                ->beforeNormalization()
                                    ->ifTrue(static fn ($v) => 0 === $v)
                                    ->then(static fn ($v) => null)
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn ($v) => null !== $v && ($v < 100 || $v > 599))
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
}
