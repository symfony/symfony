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
use Symfony\Component\Security\Http\Command\OidcTokenGenerateCommand;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Configures a token handler for decoding and validating an OIDC token.
 */
class OidcTokenHandlerFactory implements TokenHandlerFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array|string $config): void
    {
        if (null === $config['enforce_at_jwt_type']) {
            trigger_deprecation('symfony/security-bundle', '8.2', 'Not setting the "enforce_at_jwt_type" option of the "oidc" token handler is deprecated, set it explicitly; it will default to true in 9.0.');

            $config['enforce_at_jwt_type'] = false;
        }

        // a lone identifier is passed as it was given, so that an environment variable holding the
        // whole list, as "%env(json:AUDIENCES)%" does, reaches the handler as the list it resolves to
        $audience = [0] === array_keys($config['audience']) ? $config['audience'][0] : $config['audience'];

        $tokenHandlerDefinition = $container->setDefinition($id, (new ChildDefinition('security.access_token_handler.oidc'))
            ->replaceArgument(2, $audience)
            ->replaceArgument(3, $config['issuers'])
            ->replaceArgument(4, $config['claim'])
            ->replaceArgument(7, $config['allowed_time_drift'])
            ->replaceArgument(8, $config['enforce_at_jwt_type'])
            ->addTag('container.reversible')
        );

        if (!ContainerBuilder::willBeAvailable('web-token/jwt-library', Algorithm::class, ['symfony/security-bundle'])) {
            throw new LogicException('You cannot use the "oidc" token handler since "web-token/jwt-library" is not installed. Try running "composer require web-token/jwt-library".');
        }

        $tokenHandlerDefinition->replaceArgument(0, (new ChildDefinition('security.access_token_handler.oidc.signature'))
            ->replaceArgument(0, $config['algorithms']));

        if (isset($config['discovery'])) {
            if (!ContainerBuilder::willBeAvailable('symfony/http-client', HttpClientInterface::class, ['symfony/security-bundle'])) {
                throw new LogicException('You cannot use the "oidc" token handler with "discovery" since the HttpClient component is not installed. Try running "composer require symfony/http-client".');
            }

            // disable JWKSet argument
            $tokenHandlerDefinition->replaceArgument(1, null);

            $clients = [];
            foreach ($config['discovery']['base_uri'] as $uri) {
                $clients[] = (new ChildDefinition('security.access_token_handler.oidc_discovery.http_client'))
                    ->replaceArgument(0, ['base_uri' => $uri]);
            }

            $tokenHandlerDefinition->addMethodCall('enableDiscovery', [
                new Reference($config['discovery']['cache']['id']),
                $clients,
                "$id.oidc_configuration",
                $config['discovery']['enforce_key_usage_verification'],
            ]);

            $tokenHandlerDefinition->addTag('kernel.reset', ['method' => 'reset']);

            return;
        }

        $tokenHandlerDefinition->replaceArgument(1, (new ChildDefinition('security.access_token_handler.oidc.jwkset'))
            ->replaceArgument(0, $config['keyset']));

        if ($config['encryption']['enabled']) {
            $algorithmManager = (new ChildDefinition('security.access_token_handler.oidc.encryption'))
                ->replaceArgument(0, $config['encryption']['algorithms']);
            $keyset = (new ChildDefinition('security.access_token_handler.oidc.jwkset'))
                ->replaceArgument(0, $config['encryption']['keyset']);

            $tokenHandlerDefinition->addMethodCall(
                'enableJweSupport',
                [
                    $keyset,
                    $algorithmManager,
                    $config['encryption']['enforce'],
                ]
            );
        }

        // Generate command
        if (!class_exists(OidcTokenGenerateCommand::class)) {
            return;
        }

        if (!$container->hasDefinition('security.access_token_handler.oidc.command.generate')) {
            $container
                ->register('security.access_token_handler.oidc.command.generate', OidcTokenGenerateCommand::class)
                ->addTag('console.command')
            ;
        }

        $firewall = substr($id, \strlen('security.access_token_handler.'));
        $container->getDefinition('security.access_token_handler.oidc.command.generate')
            ->addMethodCall('addGenerator', [
                $firewall,
                (new ChildDefinition('security.access_token_handler.oidc.generator'))
                    ->replaceArgument(0, (new ChildDefinition('security.access_token_handler.oidc.signature'))->replaceArgument(0, $config['algorithms']))
                    ->replaceArgument(1, (new ChildDefinition('security.access_token_handler.oidc.jwkset'))->replaceArgument(0, $config['keyset']))
                    ->replaceArgument(2, $audience)
                    ->replaceArgument(3, $config['issuers'])
                    ->replaceArgument(4, $config['claim']),
                $config['algorithms'],
                $config['issuers'],
            ])
        ;
    }

    public function getKey(): string
    {
        return 'oidc';
    }

    public function addConfiguration(NodeBuilder $node): void
    {
        $node
            ->arrayNode($this->getKey())
                ->validate()
                    ->ifTrue(static fn ($v) => !isset($v['discovery']) && !isset($v['keyset']))
                    ->thenInvalid('You must set either "discovery" or "keyset".')
                ->end()
                ->children()
                    ->arrayNode('discovery')
                        ->info('Enable the OIDC discovery.')
                        ->children()
                            ->arrayNode('base_uri')
                                ->acceptAndWrap(['string'])
                                ->info('Base URI of the OIDC server.')
                                ->isRequired()
                                ->scalarPrototype()->end()
                            ->end()
                            ->arrayNode('cache')
                                ->children()
                                    ->scalarNode('id')
                                        ->info('Cache service id to use to cache the OIDC discovery configuration.')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                            ->end()
                            ->booleanNode('enforce_key_usage_verification')
                                ->info('When enabled (default), only keys explicitly designated for signature (via "use":"sig" or a "key_ops" entry containing "sign"/"verify") are accepted. When disabled, keys without any usage designation are also accepted; keys explicitly restricted to encryption are still rejected.')
                                ->defaultTrue()
                            ->end()
                        ->end()
                    ->end()
                    ->scalarNode('claim')
                        ->info('Claim which contains the user identifier (e.g.: sub, email..).')
                        ->defaultValue('sub')
                    ->end()
                    ->arrayNode('audience')
                        ->info('Identifiers of this resource server, one of which the "aud" of the token must name. A single identifier may be given as a string.')
                        ->isRequired()
                        ->requiresAtLeastOneElement()
                        ->acceptAndWrap(['string'])
                        ->scalarPrototype()->cannotBeEmpty()->end()
                    ->end()
                    ->arrayNode('issuers', 'issuer')
                        ->info('Issuers allowed to generate the token, for validation purpose.')
                        ->isRequired()
                        ->scalarPrototype()->end()
                    ->end()
                    ->arrayNode('algorithms', 'algorithm')
                        ->info('The signature algorithms the token is accepted to be signed with, among "RS256", "RS384", "RS512", "ES256", "ES384", "ES512", "PS256", "PS384" and "PS512". Defaults to "RS256", the only algorithm OIDC Core 1.0 requires providers to support; list the one your provider announces in "id_token_signing_alg_values_supported" when it signs with another. Another algorithm is accepted once its service is tagged "security.access_token_handler.oidc.signature_algorithm". No HMAC algorithm is tagged, so that a public key can never be used as a shared secret.')
                        ->defaultValue(['RS256'])
                        ->requiresAtLeastOneElement()
                        ->scalarPrototype()->cannotBeEmpty()->end()
                    ->end()
                    ->scalarNode('keyset')
                        ->info('JSON-encoded JWKSet used to sign the token (must contain a list of valid public keys).')
                    ->end()
                    ->arrayNode('encryption')
                        ->canBeEnabled()
                        ->children()
                            ->booleanNode('enforce')
                                ->info('When enabled, the token shall be encrypted.')
                                ->defaultFalse()
                            ->end()
                            ->arrayNode('algorithms', 'algorithm')
                                ->info('Algorithms used to decrypt the token.')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->end()
                            ->end()
                            ->scalarNode('keyset')
                                ->info('JSON-encoded JWKSet used to decrypt the token (must contain a list of valid private keys).')
                                ->isRequired()
                            ->end()
                        ->end()
                    ->end()
                    ->integerNode('allowed_time_drift')
                        ->info('Allowed time drift in seconds for token validation (iat, nbf, exp claims).')
                        ->defaultValue(0)
                        ->min(0)
                    ->end()
                    ->booleanNode('enforce_at_jwt_type')
                        ->info('When enabled, the "typ" header of the token must be "at+jwt" or "application/at+jwt", as RFC 9068 requires from a JWT access token. This rejects the ID tokens issued for the same audience. Disable it only for providers that do not follow the profile. Defaults to false in 8.2 and to true as of 9.0.')
                        ->defaultNull()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
