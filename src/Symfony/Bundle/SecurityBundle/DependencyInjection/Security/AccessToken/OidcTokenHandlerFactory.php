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

        $tokenHandlerDefinition->replaceArgument(1, (new ChildDefinition('security.access_token_handler.oidc.jwkset'))
            ->replaceArgument(0, $config['keyset'])
        );
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
                ->validate()
                    ->ifTrue(static fn ($v) => !isset($v['algorithm']) && !isset($v['algorithms']))
                    ->thenInvalid('You must set either "algorithm" or "algorithms".')
                ->end()
                ->validate()
                    ->ifTrue(static fn ($v) => !isset($v['key']) && !isset($v['keyset']))
                    ->thenInvalid('You must set either "key" or "keyset".')
                ->end()
                ->beforeNormalization()
                    ->ifTrue(static fn ($v) => isset($v['algorithm']) && \is_string($v['algorithm']))
                    ->then(static function ($v) {
                        if (isset($v['algorithms'])) {
                            throw new InvalidConfigurationException('You cannot use both "algorithm" and "algorithms" at the same time.');
                        }
                        $v['algorithms'] = [$v['algorithm']];
                        unset($v['algorithm']);

                        return $v;
                    })
                ->end()
                ->beforeNormalization()
                    ->ifTrue(static fn ($v) => isset($v['key']) && \is_string($v['key']))
                    ->then(static function ($v) {
                        if (isset($v['keyset'])) {
                            throw new InvalidConfigurationException('You cannot use both "key" and "keyset" at the same time.');
                        }
                        $v['keyset'] = \sprintf('{"keys":[%s]}', $v['key']);

                        return $v;
                    })
                ->end()
                ->children()
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
                    ->arrayNode('algorithm')
                        ->info('Algorithm used to sign the token.')
                        ->setDeprecated('symfony/security-bundle', '7.1', 'The "%node%" option is deprecated and will be removed in 8.0. Use the "algorithms" option instead.')
                    ->end()
                    ->arrayNode('algorithms')
                        ->info('Algorithms used to sign the token.')
                        ->isRequired()
                        ->scalarPrototype()->end()
                    ->end()
                    ->scalarNode('key')
                        ->info('JSON-encoded JWK used to sign the token (must contain a "kty" key).')
                        ->setDeprecated('symfony/security-bundle', '7.1', 'The "%node%" option is deprecated and will be removed in 8.0. Use the "keyset" option instead.')
                    ->end()
                    ->scalarNode('keyset')
                        ->info('JSON-encoded JWKSet used to sign the token (must contain a list of valid keys).')
                        ->isRequired()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
