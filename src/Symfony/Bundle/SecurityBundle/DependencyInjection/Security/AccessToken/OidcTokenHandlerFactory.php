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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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

            $clients = [];
            foreach ($config['discovery']['base_uri'] as $uri) {
                $clients[] = (new ChildDefinition('security.access_token_handler.oidc_discovery.http_client'))
                    ->replaceArgument(0, ['base_uri' => $uri]);
            }

            $tokenHandlerDefinition->addMethodCall('enableDiscovery', [
                new Reference($config['discovery']['cache']['id']),
                $clients,
                "$id.oidc_configuration",
            ]);

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
                    ->replaceArgument(2, $config['audience'])
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
                    ->ifTrue(static fn ($v) => !isset($v['algorithm']) && !isset($v['algorithms']))
                    ->thenInvalid('You must set either "algorithm" or "algorithms".')
                ->end()
                ->validate()
                    ->ifTrue(static fn ($v) => !isset($v['discovery']) && !isset($v['key']) && !isset($v['keyset']))
                    ->thenInvalid('You must set either "discovery" or "key" or "keyset".')
                ->end()
                ->beforeNormalization()
                    ->ifArray()
                    ->then(static function ($v) {
                        if (isset($v['algorithms']) && isset($v['algorithm'])) {
                            throw new InvalidConfigurationException('You cannot use both "algorithm" and "algorithms" at the same time.');
                        }
                        if (\is_string($v['algorithm'] ?? null)) {
                            $v['algorithms'] = [$v['algorithm']];
                            unset($v['algorithm']);
                        }

                        return $v;
                    })
                ->end()
                ->beforeNormalization()
                    ->ifArray()
                    ->then(static function ($v) {
                        if (isset($v['keyset']) && isset($v['key'])) {
                            throw new InvalidConfigurationException('You cannot use both "key" and "keyset" at the same time.');
                        }
                        if (\is_string($v['key'] ?? null)) {
                            $v['keyset'] = \sprintf('{"keys":[%s]}', $v['key']);
                        }

                        return $v;
                    })
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
                    ->arrayNode('issuers', 'issuer')
                        ->info('Issuers allowed to generate the token, for validation purpose.')
                        ->isRequired()
                        ->scalarPrototype()->end()
                    ->end()
                    ->arrayNode('algorithm')
                        ->info('Algorithm used to sign the token.')
                        ->setDeprecated('symfony/security-bundle', '7.1', 'The "%node%" option is deprecated and will be removed in 8.0. Use the "algorithms" option instead.')
                    ->end()
                    ->arrayNode('algorithms', 'algorithm')
                        ->info('Algorithms used to sign the token.')
                        ->isRequired()
                        ->scalarPrototype()->end()
                    ->end()
                    ->scalarNode('key')
                        ->info('JSON-encoded JWK used to sign the token (must contain a "kty" key).')
                        ->setDeprecated('symfony/security-bundle', '7.1', 'The "%node%" option is deprecated and will be removed in 8.0. Use the "keyset" option instead.')
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
                ->end()
            ->end()
        ;
    }
}
