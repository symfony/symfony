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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Configures a token handler for decoding and validating an OIDC token.
 */
class OidcTokenHandlerFactory implements TokenHandlerFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array|string $config): void
    {
        $tokenHandlerDefinition = $container->setDefinition($id, (new ChildDefinition('security.access_token_handler.oidc'))
            ->replaceArgument(2, $config['audience'])
            ->replaceArgument(3, $config['issuers'])
            ->replaceArgument(4, $config['claim'])
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
            $tokenHandlerDefinition->addMethodCall(
                'enableDiscovery',
                [
                    new Reference($config['discovery']['cache']['id']),
                    (new ChildDefinition('security.access_token_handler.oidc_discovery.http_client'))
                        ->replaceArgument(0, ['base_uri' => $config['discovery']['base_uri']]),
                    "$id.oidc_configuration",
                    "$id.oidc_jwk_set",
                ]
            );

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
    }

    public function getKey(): string
    {
        return 'oidc';
    }

    public function addConfiguration(NodeBuilder $node): void
    {
        $node
            ->arrayNode($this->getKey())
                ->fixXmlConfig($this->getKey())
                ->fixXmlConfig('algorithm')
                ->validate()
                    ->ifTrue(static fn ($v) => !isset($v['discovery']) && !isset($v['keyset']))
                    ->thenInvalid('You must set either "discovery" or "keyset".')
                ->end()
                ->children()
                    ->arrayNode('discovery')
                        ->info('Enable the OIDC discovery.')
                        ->children()
                            ->scalarNode('base_uri')
                                ->info('Base URI of the OIDC server.')
                                ->isRequired()
                                ->cannotBeEmpty()
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
                        ->end()
                    ->end()
                    ->scalarNode('claim')
                        ->info('Claim which contains the user identifier (e.g.: sub, email..).')
                        ->defaultValue('sub')
                    ->end()
                    ->scalarNode('audience')
                        ->info('Audience set in the token, for validation purpose.')
                        ->isRequired()
                    ->end()
                    ->arrayNode('issuers')
                        ->info('Issuers allowed to generate the token, for validation purpose.')
                        ->isRequired()
                        ->scalarPrototype()->end()
                    ->end()
                    ->arrayNode('algorithms')
                        ->info('Algorithms used to sign the token.')
                        ->isRequired()
                        ->scalarPrototype()->end()
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
                            ->arrayNode('algorithms')
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
                ->end()
            ->end()
        ;
    }
}
