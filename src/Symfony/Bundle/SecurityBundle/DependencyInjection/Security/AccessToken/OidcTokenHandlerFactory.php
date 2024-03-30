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

        if (!ContainerBuilder::willBeAvailable('web-token/jwt-core', Algorithm::class, ['symfony/security-bundle'])) {
            throw new LogicException('You cannot use the "oidc" token handler since "web-token/jwt-core" is not installed. Try running "composer require web-token/jwt-core".');
        }

        // @see Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SignatureAlgorithmFactory
        // for supported algorithms
        if (\in_array($config['algorithm'], ['ES256', 'ES384', 'ES512'], true)) {
            $tokenHandlerDefinition->replaceArgument(0, new Reference('security.access_token_handler.oidc.signature.'.$config['algorithm']));
        } else {
            $tokenHandlerDefinition->replaceArgument(0, (new ChildDefinition('security.access_token_handler.oidc.signature'))
                ->replaceArgument(0, $config['algorithm'])
            );
        }

        $tokenHandlerDefinition->replaceArgument(1, (new ChildDefinition('security.access_token_handler.oidc.jwk'))
            ->replaceArgument(0, $config['key'])
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
                        ->prototype('scalar')->end()
                    ->end()
                    ->scalarNode('algorithm')
                        ->info('Algorithm used to sign the token.')
                        ->isRequired()
                    ->end()
                    ->scalarNode('key')
                        ->info('JSON-encoded JWK used to sign the token (must contain a "kty" key).')
                        ->isRequired()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
