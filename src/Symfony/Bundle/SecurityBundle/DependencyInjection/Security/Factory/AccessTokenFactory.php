<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\TokenHandlerFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

/**
 * AccessTokenFactory creates services for Access Token authentication.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
final class AccessTokenFactory extends AbstractFactory implements StatelessAuthenticatorFactoryInterface
{
    private const PRIORITY = -40;

    private const WELL_KNOWN_PATH = '/.well-known/oauth-protected-resource';

    private const EXTRACTOR_ALIASES = [
        'query_string' => 'security.access_token_extractor.query_string',
        'request_body' => 'security.access_token_extractor.request_body',
        'header' => 'security.access_token_extractor.header',
    ];

    /**
     * The RFC 6750 method each built-in extractor implements, as named by the
     * "bearer_methods_supported" metadata parameter of RFC 9728, Section 2.
     */
    private const BEARER_METHODS = [
        'security.access_token_extractor.header' => 'header',
        'security.access_token_extractor.request_body' => 'body',
        'security.access_token_extractor.query_string' => 'query',
    ];

    /**
     * @param array<TokenHandlerFactoryInterface> $tokenHandlerFactories
     */
    public function __construct(private readonly array $tokenHandlerFactories)
    {
        $this->options = [];
        $this->defaultFailureHandlerOptions = [];
        $this->defaultSuccessHandlerOptions = [];
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        parent::addConfiguration($node);

        $builder = $node->children();
        $builder
            ->scalarNode('realm')->defaultNull()->end()
            ->arrayNode('token_extractors', 'token_extractor')
                ->acceptAndWrap(['string'])
                ->cannotBeEmpty()
                ->defaultValue(['security.access_token_extractor.header'])
                ->scalarPrototype()->end()
            ->end()
            ->arrayNode('token_handler')
                ->example(['id' => 'App\Security\CustomTokenHandler'])
                ->acceptAndWrap(['string'], 'id')

                ->validate()
                    ->ifTrue(static fn ($v) => \is_array($v) && 1 < \count($v))
                    ->then(static fn () => throw new InvalidConfigurationException('You cannot configure multiple token handlers.'))
                ->end()

                // "isRequired" must be set otherwise the following custom validation is not called
                ->isRequired()
                ->validate()
                    ->ifTrue(static fn ($v) => \is_array($v) && !$v)
                    ->then(static fn () => throw new InvalidConfigurationException('You must set a token handler.'))
                ->end()

                ->children()
                    ->appendFromCallback(function (NodeBuilder $tokenHandlerNodeBuilder) {
                        foreach ($this->tokenHandlerFactories as $factory) {
                            $factory->addConfiguration($tokenHandlerNodeBuilder);
                        }
                    })
                ->end()
            ->end()
            ->arrayNode('resource_metadata')
                ->info('Declaring this node serves the RFC 9728 protected resource metadata document of the firewall at "/.well-known/oauth-protected-resource" and advertises its URL in the "resource_metadata" parameter of the "WWW-Authenticate" header, which is how a client discovers where to get a token this firewall accepts. The route is declared by the "security.authenticator.access_token.route_loader" service, which the application must import as it does for the logout routes; make sure it is reachable without a token.')
                ->treatNullLike([])
                ->children()
                    ->scalarNode('resource')
                        ->defaultNull()
                        ->info('The resource identifier of this firewall: an HTTPS URL, without a fragment (e.g. "https://api.example.com" or "https://example.com/api"). Its path component is inserted after the well-known path, as RFC 9728, Section 3.1 prescribes, so that one host can serve the metadata of several protected resources. Defaults to the origin the document is served from, which is what a firewall covering a whole application wants.')
                        ->validate()
                            ->ifTrue(static function ($v): bool {
                                if (false === $parts = parse_url((string) $v)) {
                                    return true;
                                }

                                // RFC 9728, Section 3.1 derives the well-known path from the path
                                // component of this identifier, and the route carrying that path is
                                // declared at build time, so a value parsing as no URL at all leaves
                                // the document and the route disagreeing; an environment variable
                                // interpolated into the URL keeps the path readable and is accepted
                                if (!isset($parts['scheme'], $parts['host'])) {
                                    return true;
                                }

                                // a query component is only a SHOULD NOT, which RFC 8707, Section 2
                                // relaxes for the cases that need one, so it is accepted here
                                return isset($parts['fragment']) || !OidcDiscovery::isSecureUrl((string) $v);
                            })
                            ->thenInvalid('The protected resource "resource" identifier must be an HTTPS URL without a fragment (got %s), as RFC 9728, Section 1.2 requires. A loopback host (localhost, 127.0.0.1, ::1, *.localhost) is accepted over HTTP for local development. It is read when the route is declared, so an environment variable holding the whole value leaves the path unknown: interpolate it into the URL instead, as in "https://%%env(API_HOST)%%/v1".')
                        ->end()
                    ->end()
                    ->arrayNode('authorization_servers', 'authorization_server')
                        ->acceptAndWrap(['string'])
                        ->scalarPrototype()->end()
                        ->defaultValue([])
                        ->info('The issuer identifiers of the authorization servers issuing the access tokens this firewall accepts (e.g. "https://accounts.example.com"). This is what tells a client holding no token where to go and get one.')
                    ->end()
                    ->scalarNode('jwks_uri')
                        ->defaultNull()
                        ->info('URL of the JWK Set holding the keys this resource signs its own responses with. Unrelated to the keys the access tokens are verified against, which belong to the authorization server.')
                    ->end()
                    ->arrayNode('scopes_supported', 'scope')
                        ->acceptAndWrap(['string'])
                        ->scalarPrototype()->end()
                        ->defaultValue([])
                        ->info('The scope values this resource uses, one entry per scope.')
                    ->end()
                    ->arrayNode('bearer_methods_supported', 'bearer_method')
                        ->acceptAndWrap(['string'])
                        ->enumPrototype()->values(['header', 'body', 'query'])->end()
                        ->defaultValue([])
                        ->info('The ways this resource accepts to be handed a bearer token, among "header", "body" and "query" (RFC 6750, Sections 2.1 to 2.3). Deduced from "token_extractors" when left empty; set it explicitly when a custom extractor makes that deduction incomplete.')
                    ->end()
                    ->scalarNode('resource_name')
                        ->defaultNull()
                        ->info('Human-readable name of this resource, meant to be displayed to the end user.')
                    ->end()
                    ->scalarNode('resource_documentation')
                        ->defaultNull()
                        ->info('URL of the developer documentation of this resource.')
                    ->end()
                    ->scalarNode('resource_policy_uri')
                        ->defaultNull()
                        ->info('URL of the policy telling how the client may use the data this resource exposes.')
                    ->end()
                    ->scalarNode('resource_tos_uri')
                        ->defaultNull()
                        ->info('URL of the terms of service of this resource.')
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getKey(): string
    {
        return 'access_token';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, ?string $userProviderId): string
    {
        $successHandler = isset($config['success_handler']) ? new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)) : null;
        $failureHandler = isset($config['failure_handler']) ? new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)) : null;
        $authenticatorId = \sprintf('security.authenticator.access_token.%s', $firewallName);
        $extractorId = $this->createExtractor($container, $firewallName, $config['token_extractors']);
        $tokenHandlerId = $this->createTokenHandler($container, $firewallName, $config['token_handler'], $userProviderId);

        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.access_token'))
            ->replaceArgument(0, new Reference($tokenHandlerId))
            ->replaceArgument(1, new Reference($extractorId))
            ->replaceArgument(2, $userProviderId ? new Reference($userProviderId) : null)
            ->replaceArgument(3, $successHandler)
            ->replaceArgument(4, $failureHandler)
            ->replaceArgument(5, $config['realm'])
            ->replaceArgument(6, $resourceMetadataUri = isset($config['resource_metadata']) ? $this->createResourceMetadata($container, $firewallName, $config['resource_metadata'], $config['token_extractors']) : null)
        ;

        $this->createFallbackAccessDeniedHandler($container, $firewallName, $config['realm'], $resourceMetadataUri);

        return $authenticatorId;
    }

    /**
     * Registers the access denied handler the firewall falls back on, unless it configures one of its own,
     * so that a denial caused by a missing scope gets the RFC 6750 §3.1 "insufficient_scope" challenge.
     */
    private function createFallbackAccessDeniedHandler(ContainerBuilder $container, string $firewallName, ?string $realm, ?string $resourceMetadataUri): void
    {
        $container
            ->setDefinition(\sprintf('security.fallback_access_denied_handler.%s', $firewallName), new ChildDefinition('security.access_token.access_denied_handler'))
            ->replaceArgument(0, $realm)
            ->replaceArgument(2, $resourceMetadataUri)
        ;
    }

    /**
     * @param array<string> $extractors
     */
    private function createExtractor(ContainerBuilder $container, string $firewallName, array $extractors): string
    {
        $extractors = array_map(static fn ($extractor) => self::EXTRACTOR_ALIASES[$extractor] ?? $extractor, $extractors);

        if (1 === \count($extractors)) {
            return current($extractors);
        }
        $extractorId = \sprintf('security.authenticator.access_token.chain_extractor.%s', $firewallName);
        $container
            ->setDefinition($extractorId, new ChildDefinition('security.authenticator.access_token.chain_extractor'))
            ->replaceArgument(0, array_map(static fn (string $extractorId): Reference => new Reference($extractorId), $extractors))
        ;

        return $extractorId;
    }

    /**
     * Registers the RFC 9728 metadata document of the firewall and the route serving it,
     * and returns the URL to advertise for it in the "WWW-Authenticate" header.
     *
     * @param array<string> $extractors
     */
    private function createResourceMetadata(ContainerBuilder $container, string $firewallName, array $config, array $extractors): string
    {
        $parts = null === $config['resource'] ? [] : parse_url($config['resource']);
        $origin = isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : null;

        if (null === $origin) {
            // the identifier pins no origin, so the URL can only be resolved against the request
            $path = $uri = self::WELL_KNOWN_PATH;
        } else {
            // RFC 9728, Section 3.1: the well-known path is inserted between the host component
            // and the path and query components, the terminating slash of the host removed first
            $path = self::WELL_KNOWN_PATH.rtrim($parts['path'] ?? '', '/');
            $uri = $origin.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        $paths = $container->hasParameter('security.access_token.resource_metadata_paths') ? (array) $container->getParameter('security.access_token.resource_metadata_paths') : [];
        $paths[$firewallName] = $path;
        $container->setParameter('security.access_token.resource_metadata_paths', $paths);

        // every parameter the firewall configuration already carries is derived from it, so
        // that the document keeps describing what the firewall enforces instead of a copy of
        // it kept in sync by hand; a protocol feature added later derives its own the same way
        $metadata = [
            'resource' => $config['resource'],
            'authorization_servers' => $config['authorization_servers'],
            'jwks_uri' => $config['jwks_uri'],
            'scopes_supported' => $config['scopes_supported'],
            'bearer_methods_supported' => $config['bearer_methods_supported'] ?: $this->createBearerMethods($extractors),
            'resource_name' => $config['resource_name'],
            'resource_documentation' => $config['resource_documentation'],
            'resource_policy_uri' => $config['resource_policy_uri'],
            'resource_tos_uri' => $config['resource_tos_uri'],
        ];

        $controller = $container->getDefinition('security.authenticator.access_token.protected_resource_metadata_controller');
        // RFC 9728, Section 3.2: a metadata parameter with zero values is omitted
        $controller->replaceArgument(0, $controller->getArgument(0) + [$firewallName => array_filter($metadata, static fn ($value): bool => null !== $value && [] !== $value)]);

        return $uri;
    }

    /**
     * @param array<string> $extractors
     *
     * @return array<string>
     */
    private function createBearerMethods(array $extractors): array
    {
        $methods = [];
        foreach ($extractors as $extractor) {
            // a custom extractor implements a method this configuration cannot name, so it
            // contributes nothing rather than making the document claim something wrong
            if (null !== $method = self::BEARER_METHODS[self::EXTRACTOR_ALIASES[$extractor] ?? $extractor] ?? null) {
                $methods[$method] = $method;
            }
        }

        return array_values($methods);
    }

    private function createTokenHandler(ContainerBuilder $container, string $firewallName, array $config, ?string $userProviderId): string
    {
        $key = array_keys($config)[0];
        $id = \sprintf('security.access_token_handler.%s', $firewallName);

        foreach ($this->tokenHandlerFactories as $factory) {
            if ($key !== $factory->getKey()) {
                continue;
            }

            $factory->create($container, $id, $config[$key], $userProviderId);
        }

        return $id;
    }
}
