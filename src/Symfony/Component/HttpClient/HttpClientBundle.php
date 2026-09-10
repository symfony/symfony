<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Http\Client\HttpAsyncClient;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\DependencyInjection\HttpClientPass;
use Symfony\Component\HttpClient\Exception\ChunkCacheItemNotFoundException;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Provides the HTTP client services.
 */
#[RequiredBundle(ServicesBundle::class)]
class HttpClientBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        // the container can drop the data collector this pass looks for, so run after it
        $container->addCompilerPass(new HttpClientPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -16);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
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
                        ->arrayNode('vars', 'var')
                            ->info('Associative array: the default vars used to expand the templated URI.')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                        ->appendFromCallback($this->appendCommonOptions(...))
                    ->end()
                ->end()
                ->scalarNode('mock_response_factory')
                    ->info('`true` to always return empty 200 responses, or the id of the service to use to generate mock responses - which should be either an invokable or an iterable.')
                ->end()
                ->arrayNode('scoped_clients', 'scoped_client')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->acceptAndWrap(['string'], 'base_uri')
                        ->validate()
                            ->ifTrue(static fn ($v) => !isset($v['scope']) && !isset($v['base_uri']))
                            ->thenInvalid('Either "scope" or "base_uri" should be defined.')
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => !empty($v['query']) && !isset($v['base_uri']))
                            ->thenInvalid('"query" applies to "base_uri" but no base URI is defined.')
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => !empty($v['retry_failed']['base_uris']) && !isset($v['base_uri']))
                            ->thenInvalid('"retry_failed.base_uris" lists the URIs to fall back to, so a "base_uri" must be defined as well.')
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
                            ->scalarNode('mock_response_factory')
                                ->info('`true` to always return empty 200 responses, `false` to disable mocking, or the id of the service to use to generate mock responses (invokable or iterable).')
                            ->end()
                            ->appendFromCallback($this->appendCommonOptions(...))
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/http_client.php');

        if ($container->getParameter('kernel.debug')) {
            $configurator->import('Resources/config/http_client_debug.php');
        }

        $options = $config['default_options'] ?? [];
        $cachingOptions = $options['caching'] ?? ['enabled' => false];
        unset($options['caching']);
        $rateLimiter = $options['rate_limiter'] ?? null;
        unset($options['rate_limiter']);
        $retryOptions = $options['retry_failed'] ?? ['enabled' => false];
        unset($options['retry_failed']);
        $defaultUriTemplateVars = $options['vars'] ?? [];
        unset($options['vars']);
        $container->getDefinition('http_client.transport')->setArguments([$options, $config['max_host_connections'] ?? 6]);

        if (!$hasPsr18 = ContainerBuilder::willBeAvailable('psr/http-client', ClientInterface::class, ['symfony/http-client'])) {
            $container->removeDefinition('psr18.http_client');
            $container->removeAlias(ClientInterface::class);
        }

        if (!$hasHttplug = ContainerBuilder::willBeAvailable('php-http/httplug', HttpAsyncClient::class, ['symfony/http-client'])) {
            $container->removeDefinition('httplug.http_client');
            $container->removeAlias(HttpAsyncClient::class);
        }

        if ($cachingOptions['enabled']) {
            $this->registerCachingClient($cachingOptions, $options, 'http_client', $container);
        }

        if (null !== $rateLimiter) {
            $this->registerThrottlingClient($rateLimiter, 'http_client', $container);
        }

        if ($retryOptions['enabled']) {
            $this->registerRetryableClient($retryOptions, 'http_client', $container);
        }

        if (ContainerBuilder::willBeAvailable('guzzlehttp/uri-template', \GuzzleHttp\UriTemplate\UriTemplate::class, [])) {
            $container->setAlias('http_client.uri_template_expander', 'http_client.uri_template_expander.guzzle');
        } elseif (ContainerBuilder::willBeAvailable('rize/uri-template', \Rize\UriTemplate::class, [])) {
            $container->setAlias('http_client.uri_template_expander', 'http_client.uri_template_expander.rize');
        }

        $container
            ->getDefinition('http_client.uri_template')
            ->setArgument(2, $defaultUriTemplateVars);

        if (!$defaultMockResponseFactory = $config['mock_response_factory'] ?? null) {
            $defaultTransportId = 'http_client.transport';
        } elseif (\is_string($defaultMockResponseFactory)) {
            $defaultTransportId = '.http_client.mock_transport.'.$defaultMockResponseFactory;
            $container->register($defaultTransportId, MockHttpClient::class)
                ->setArguments([new Reference($defaultMockResponseFactory)])
                ->addTag('kernel.reset', ['method' => 'reset']);
        } else {
            $defaultTransportId = 'http_client.mock_transport';
        }

        $realTransportId = 'http_client.transport';

        if ('http_client.transport' !== $defaultTransportId) {
            // Decorate "http_client.transport" instead of replacing it as the transport of "http_client", so that
            // decorators registered on "http_client.transport" remain in the chain when a mock factory is configured.
            // The highest priority makes the mock the innermost decorator: decorators keep running around it whatever
            // their own priority. The undecorated transport stays available under "http_client.transport.real" for
            // scoped clients that opt out with "mock_response_factory: false".
            $container->getDefinition($defaultTransportId)
                ->setDecoratedService('http_client.transport', $realTransportId = 'http_client.transport.real', \PHP_INT_MAX);
            $defaultTransportId = 'http_client.transport';
        }

        foreach ($config['scoped_clients'] as $name => $scopeConfig) {
            if ($container->has($name)) {
                throw new InvalidArgumentException(\sprintf('Invalid scope name: "%s" is reserved.', $name));
            }

            $scope = $scopeConfig['scope'] ?? null;
            unset($scopeConfig['scope']);
            $cachingOptions = $scopeConfig['caching'] ?? ['enabled' => false];
            unset($scopeConfig['caching']);
            $rateLimiter = $scopeConfig['rate_limiter'] ?? null;
            unset($scopeConfig['rate_limiter']);
            $retryOptions = $scopeConfig['retry_failed'] ?? ['enabled' => false];
            unset($scopeConfig['retry_failed']);

            // the base URI is the first one tried and the configured list holds the fallbacks; the
            // scoping and the retryable clients must agree on the whole set
            if ($retryOptions['base_uris'] ?? []) {
                $retryOptions['base_uris'] = array_merge([$scopeConfig['base_uri']], $retryOptions['base_uris']);
            }

            if (false === $mockResponseFactory = $scopeConfig['mock_response_factory'] ?? $defaultMockResponseFactory) {
                $transportId = $realTransportId;
            } elseif ($mockResponseFactory === $defaultMockResponseFactory) {
                $transportId = $defaultTransportId;
            } elseif (\is_string($mockResponseFactory)) {
                $transportId = '.http_client.mock_transport.'.$mockResponseFactory;
                $container->register($transportId, MockHttpClient::class)
                    ->setArguments([new Reference($mockResponseFactory)])
                    ->addTag('kernel.reset', ['method' => 'reset']);
            } else {
                $transportId = 'http_client.mock_transport';
            }
            unset($scopeConfig['mock_response_factory']);

            // This "transport" service is decorated in the following order:
            // 1. ThrottlingHttpClient (5) -> throttles requests
            // 2. UriTemplateHttpClient (10) -> expands URI templates
            // 3. ScopingHttpClient (15) -> resolves relative URLs and applies scope configuration
            // 4. CachingHttpClient (20) -> caches responses
            // 5. RetryableHttpClient (25) -> retries requests
            // 6. TraceableHttpClient (100) -> traces requests
            //
            // when "retry_failed.base_uris" is set, RetryableHttpClient moves to 12 so that it
            // wraps ScopingHttpClient instead of being wrapped by it, see below
            $container->register($name, HttpClientInterface::class)
                ->setFactory('current')
                ->setArguments([[new Reference($transportId)]])
                ->addTag('http_client.client')
            ;

            $scopingDefinition = $container->register($name.'.scoping', ScopingHttpClient::class)
                ->setDecoratedService($name, null, 15)
                ->addTag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore']);

            if (null === $scope) {
                $baseUri = $scopeConfig['base_uri'];
                unset($scopeConfig['base_uri']);

                if ($retryOptions['base_uris'] ?? []) {
                    // the scope must match every URI the retryable client may rotate to, otherwise
                    // the scoped options stop applying as soon as it leaves the first one
                    $scopingDefinition
                        ->setFactory([ScopingHttpClient::class, 'forBaseUris'])
                        ->setArguments([new Reference('.inner'), $retryOptions['base_uris'], $scopeConfig]);
                } else {
                    $scopingDefinition
                        ->setFactory([ScopingHttpClient::class, 'forBaseUri'])
                        ->setArguments([new Reference('.inner'), $baseUri, $scopeConfig]);
                }
            } else {
                $scopingDefinition
                    ->setArguments([new Reference('.inner'), [$scope => $scopeConfig], $scope]);
            }

            if ($cachingOptions['enabled']) {
                $this->registerCachingClient($cachingOptions, $scopeConfig, $name, $container);
            }

            if (null !== $rateLimiter) {
                $this->registerThrottlingClient($rateLimiter, $name, $container);
            }

            if ($retryOptions['enabled']) {
                $this->registerRetryableClient($retryOptions, $name, $container);
            }

            $container
                ->register($name.'.uri_template', UriTemplateHttpClient::class)
                ->setDecoratedService($name, null, 10)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference('http_client.uri_template_expander', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    $defaultUriTemplateVars,
                ]);

            $container->registerAliasForArgument($name, HttpClientInterface::class);

            if ($hasPsr18) {
                $container->setDefinition('psr18.'.$name, new ChildDefinition('psr18.http_client'))
                    ->replaceArgument(0, new Reference($name));

                $container->registerAliasForArgument('psr18.'.$name, ClientInterface::class, $name);
            }

            if ($hasHttplug) {
                $container->setDefinition('httplug.'.$name, new ChildDefinition('httplug.http_client'))
                    ->replaceArgument(0, new Reference($name));

                $container->registerAliasForArgument('httplug.'.$name, HttpAsyncClient::class, $name);
            }
        }
    }

    private function appendCommonOptions(NodeBuilder $builder): void
    {
        $builder
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
            ->floatNode('max_connect_duration')
                ->info('The maximum duration allowed for DNS + TCP + TLS connection; a value lower than or equal to 0 means unlimited.')
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
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->variablePrototype()->end()
            ->end()
            ->scalarNode('rate_limiter')
                ->defaultNull()
                ->info('Rate limiter name to use for throttling requests.')
            ->end()
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
                        ->info('The maximum TTL (in seconds) allowed for cached responses.')
                        ->defaultValue(86400)
                        ->min(1)
                        ->beforeNormalization()
                            ->ifNull()
                            ->then(static function () {
                                trigger_deprecation('symfony/framework-bundle', '8.1', 'Setting "framework.http_client.default_options.caching.max_ttl" to "null" is deprecated, use a positive integer instead.');

                                return 86400;
                            })
                        ->end()
                    ->end()
                ->end()
            ->end()
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
                ->validate()
                    ->ifTrue(static fn ($v) => $v['base_uris'] && !$v['enabled'])
                    ->thenInvalid('The "base_uris" option requires retries to be enabled.')
                ->end()
                ->children()
                    ->arrayNode('base_uris')
                        ->info('Additional URIs to retry against, tried in turn after the base URI, one per attempt.')
                        ->beforeNormalization()->ifString()->then(static fn ($v) => [$v])->end()
                        ->stringPrototype()->cannotBeEmpty()->end()
                    ->end()
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
            ->end()
        ;
    }

    private function registerCachingClient(array $options, array $defaultOptions, string $name, ContainerBuilder $container): void
    {
        if (!class_exists(ChunkCacheItemNotFoundException::class)) {
            throw new LogicException('Caching cannot be enabled as version 7.4+ of the HttpClient component is required.');
        }

        $definition = $container
            ->register($name.'.caching', CachingHttpClient::class)
            ->setDecoratedService($name, null, 20)
            ->setArguments([
                new Reference('.inner'),
                new Reference($options['cache_pool']),
                $defaultOptions,
                $options['shared'],
                $options['max_ttl'],
            ]);

        if (method_exists(CachingHttpClient::class, 'setLogger')) {
            $definition
                ->addMethodCall('setLogger', [new Reference('logger')])
                ->addTag('monolog.logger', ['channel' => 'http_client']);
        }
    }

    private function registerThrottlingClient(string $rateLimiter, string $name, ContainerBuilder $container): void
    {
        if (!interface_exists(LimiterInterface::class)) {
            throw new LogicException('Rate limiter cannot be used within HttpClient as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
        }

        $container->register($name.'.throttling.limiter', LimiterInterface::class)
            ->setFactory([new Reference('limiter.'.$rateLimiter), 'create']);

        $container
            ->register($name.'.throttling', ThrottlingHttpClient::class)
            ->setDecoratedService($name, null, 5)
            ->setArguments([new Reference('.inner'), new Reference($name.'.throttling.limiter')]);
    }

    private function registerRetryableClient(array $options, string $name, ContainerBuilder $container): void
    {
        if (null !== $options['retry_strategy']) {
            $retryStrategy = new Reference($options['retry_strategy']);
        } else {
            $retryStrategy = new ChildDefinition('http_client.abstract_retry_strategy');
            $codes = [];
            foreach ($options['http_codes'] as $code => $codeOptions) {
                if ($codeOptions['methods']) {
                    $codes[$code] = $codeOptions['methods'];
                } else {
                    $codes[] = $code;
                }
            }

            $retryStrategy
                ->replaceArgument(0, $codes ?: GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES)
                ->replaceArgument(1, $options['delay'])
                ->replaceArgument(2, $options['multiplier'])
                ->replaceArgument(3, $options['max_delay'])
                ->replaceArgument(4, $options['jitter']);
            $container->setDefinition($name.'.retry_strategy', $retryStrategy);

            $retryStrategy = new Reference($name.'.retry_strategy');
        }

        // when retrying against several URIs, the retryable client must sit outside the scoping one:
        // scoping resolves the URL and consumes the "base_uri" option, so a base URI injected below
        // it would never be applied
        $definition = $container
            ->register($name.'.retryable', RetryableHttpClient::class)
            ->setDecoratedService($name, null, $options['base_uris'] ? 12 : 25)
            ->setArguments([new Reference('.inner'), $retryStrategy, $options['max_retries'], new Reference('logger')])
            ->addTag('monolog.logger', ['channel' => 'http_client']);

        if ($options['base_uris']) {
            $definition->addMethodCall('withOptions', [['base_uri' => $options['base_uris']]], true);
        }
    }
}
