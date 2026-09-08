<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken;

use Jose\Component\Core\Algorithm;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Configures a token handler for an OAuth2 Token Introspection endpoint.
 *
 * Where the authorization server lives and how this resource server authenticates there is the
 * business of the HTTP client the "http_client" option names, not of the firewall: a scoped client
 * declares the introspection endpoint as its "base_uri" and the credentials RFC 7662 §2.1 requires
 * the endpoint to demand as its "auth_basic". Those credentials are sent as given, where the
 * "client_secret_basic" of RFC 6749 §2.3.1 form-urlencodes both halves, so a client id or a secret
 * holding a colon, a plus, a space or a non-ASCII byte must be encoded before it is passed. What is
 * configured here is only what the firewall itself needs, namely what the response is confronted
 * with.
 *
 * The JWKSet and the algorithm manager the "response_signature" option builds are the ones of the
 * "oidc" token handler: neither is tied to OpenID Connect, they turn a JSON document into a JWKSet
 * and the algorithms tagged in the bundle into a JWS algorithm manager, which is what RFC 9701 asks
 * of a resource server verifying a signed introspection response.
 *
 * @internal
 */
class OAuth2TokenHandlerFactory implements TokenHandlerFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array|string $config): void
    {
        // a lone identifier is passed as it was given, so that an environment variable holding the
        // whole list, as "%env(json:AUDIENCES)%" does, reaches the handler as the list it resolves to
        $audience = [0] === array_keys($config['audience']) ? $config['audience'][0] : $config['audience'];

        $tokenHandlerDefinition = $container->setDefinition($id, new ChildDefinition('security.access_token_handler.oauth2'))
            ->replaceArgument(2, $audience)
            ->replaceArgument(3, $config['issuer'])
            ->replaceArgument(4, $config['claim'])
            ->replaceArgument(6, $config['allowed_time_drift'])
        ;

        if (isset($config['http_client'])) {
            $tokenHandlerDefinition->replaceArgument(0, new Reference($config['http_client']));
        }

        if (isset($config['cache'])) {
            if (!ContainerBuilder::willBeAvailable('symfony/cache', CacheInterface::class, ['symfony/security-bundle'])) {
                throw new LogicException('You cannot cache the introspection responses of the "oauth2" token handler since the Cache component is not installed. Try running "composer require symfony/cache".');
            }

            $tokenHandlerDefinition->addMethodCall('enableCache', [
                new Reference($config['cache']['id']),
                "$id.introspection.",
                $config['cache']['ttl'],
            ]);
        }

        if ($config['response_signature']['enabled']) {
            if (!ContainerBuilder::willBeAvailable('web-token/jwt-library', Algorithm::class, ['symfony/security-bundle'])) {
                throw new LogicException('You cannot verify the signature of the introspection responses since "web-token/jwt-library" is not installed. Try running "composer require web-token/jwt-library".');
            }

            $tokenHandlerDefinition->addMethodCall('enableSignedResponse', [
                (new ChildDefinition('security.access_token_handler.oidc.signature'))
                    ->replaceArgument(0, $config['response_signature']['algorithms']),
                $config['response_signature']['keyset']
                    ? (new ChildDefinition('security.access_token_handler.oidc.jwkset'))->replaceArgument(0, $config['response_signature']['keyset'])
                    : null,
                $config['response_signature']['enforce'],
            ]);

            if ($config['response_signature']['discovery']['enabled']) {
                $tokenHandlerDefinition->addMethodCall('enableSignedResponseDiscovery', [
                    new Reference($config['response_signature']['discovery']['cache']['id']),
                    (new ChildDefinition('security.access_token_handler.oidc_discovery.http_client'))->replaceArgument(0, []),
                    "$id.authorization_server_metadata",
                ]);
            }
        }
    }

    public function getKey(): string
    {
        return 'oauth2';
    }

    public function addConfiguration(NodeBuilder $node): void
    {
        $node
            ->arrayNode($this->getKey())
                ->beforeNormalization()
                    ->ifString()
                    ->then(static fn ($v) => ['http_client' => $v])
                ->end()
                ->validate()
                    ->ifTrue(static fn ($v) => $v['response_signature']['enabled'] && (null === $v['issuer'] || !$v['audience']))
                    ->thenInvalid('The "issuer" and "audience" options of the "oauth2" token handler are required when "response_signature" is enabled: RFC 9701 §5 makes "iss" and "aud" mandatory claims of the response.')
                ->end()
                ->children()
                    ->scalarNode('http_client')
                        ->info('HttpClient service id the introspection endpoint is called with. Declare it as a scoped client whose "base_uri" is the introspection endpoint of your authorization server and whose "auth_basic" holds the credentials it authenticates with. Those are sent as given, where the "client_secret_basic" of RFC 6749 §2.3.1 form-urlencodes both halves, so encode a client id or a secret holding a colon, a plus or a space yourself.')
                        ->cannotBeEmpty()
                    ->end()
                    ->arrayNode('audience')
                        ->info('Identifiers of this resource server, one of which the "aud" of the introspection response must name. A single identifier may be given as a string.')
                        ->acceptAndWrap(['string'])
                        ->scalarPrototype()->cannotBeEmpty()->end()
                    ->end()
                    ->scalarNode('issuer')
                        ->info('Identifier of the authorization server, checked against the "iss" of the introspection response.')
                        ->defaultNull()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('claim')
                        ->info('Claim which contains the user identifier (e.g.: sub, username, email...). Defaults to "sub", falling back to "username".')
                        ->defaultNull()
                        ->cannotBeEmpty()
                    ->end()
                    ->integerNode('allowed_time_drift')
                        ->info('Allowed time drift in seconds when validating the "iat", "nbf" and "exp" of the introspection response.')
                        ->defaultValue(0)
                        ->min(0)
                    ->end()
                    ->arrayNode('cache')
                        ->info('Cache the introspection responses of active tokens, never beyond their "exp".')
                        ->children()
                            ->scalarNode('id')
                                ->info('Cache service id to use to cache the introspection responses.')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->integerNode('ttl')
                                ->info('Maximum lifetime in seconds of a cached introspection response. The shorter it is, the sooner a revoked token stops being accepted.')
                                ->defaultValue(60)
                                ->min(1)
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('response_signature')
                        ->info('Ask the authorization server for a signed introspection response (RFC 9701) and verify it.')
                        ->canBeEnabled()
                        ->validate()
                            ->ifTrue(static fn ($v) => $v['enabled'] && !$v['keyset'] && !$v['discovery']['enabled'])
                            ->thenInvalid('The "response_signature" option needs the keys it verifies the introspection response against: set its "keyset", or enable its "discovery" to read them from the authorization server metadata.')
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => $v['enabled'] && $v['keyset'] && $v['discovery']['enabled'])
                            ->thenInvalid('The "keyset" and "discovery" options of the "response_signature" option are exclusive: the keys come either from the configuration or from the authorization server metadata.')
                        ->end()
                        ->children()
                            ->booleanNode('enforce')
                                ->info('When enabled (default), a plain JSON introspection response is refused.')
                                ->defaultTrue()
                            ->end()
                            ->arrayNode('algorithms', 'algorithm')
                                ->info('The signature algorithms the introspection response is accepted to be signed with, among "RS256", "RS384", "RS512", "ES256", "ES384", "ES512", "PS256", "PS384" and "PS512". Defaults to "RS256", which nearly every authorization server signs with; list the ones yours announces in "introspection_signing_alg_values_supported" when it signs with another. Another algorithm is accepted once its service is tagged "security.access_token_handler.oidc.signature_algorithm". No HMAC algorithm is tagged, so that a public key can never be used as a shared secret.')
                                ->defaultValue(['RS256'])
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->cannotBeEmpty()->end()
                            ->end()
                            ->arrayNode('discovery')
                                ->info('Read the keys the introspection response is verified against from the RFC 8414 metadata of the authorization server, whose URL is derived from the "issuer" this handler already declares. Only the "jwks_uri" is read from it: which algorithms are accepted stays declared here, so that an authorization server cannot widen it by announcing more.')
                                ->canBeEnabled()
                                ->children()
                                    ->arrayNode('cache')
                                        ->addDefaultsIfNotSet()
                                        ->children()
                                            ->scalarNode('id')
                                                ->info('Cache service id the metadata document and the keys it points at are stored in.')
                                                ->defaultValue('cache.app')
                                                ->cannotBeEmpty()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('keyset')
                                ->info('JSON-encoded JWKSet holding the public keys of your authorization server, the ones it announces at its "jwks_uri", which the introspection response is verified against.')
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
