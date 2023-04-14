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
use Jose\Component\Core\JWK;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SignatureAlgorithmFactory;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Configures a token handler for decoding and validating an OIDC token.
 *
 * @experimental
 */
class OidcTokenHandlerFactory implements TokenHandlerFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array|string $config): void
    {
        $tokenHandlerDefinition = $container->setDefinition($id, new ChildDefinition('security.access_token_handler.oidc'));
        $tokenHandlerDefinition->replaceArgument(3, $config['claim']);
        $tokenHandlerDefinition->replaceArgument(4, $config['audience']);

        // Create the signature algorithm and the JWK
        if (!ContainerBuilder::willBeAvailable('web-token/jwt-core', Algorithm::class, ['symfony/security-bundle'])) {
            $container->register('security.access_token_handler.oidc.signature', 'stdClass')
                ->addError('You cannot use the "oidc" token handler since "web-token/jwt-core" is not installed. Try running "web-token/jwt-core".');
            $container->register('security.access_token_handler.oidc.jwk', 'stdClass')
                ->addError('You cannot use the "oidc" token handler since "web-token/jwt-core" is not installed. Try running "web-token/jwt-core".');
        } else {
            $container->register('security.access_token_handler.oidc.signature', Algorithm::class)
                ->setFactory([SignatureAlgorithmFactory::class, 'create'])
                ->setArguments([$config['signature']['algorithm']]);
            $container->register('security.access_token_handler.oidc.jwk', JWK::class)
                ->setFactory([JWK::class, 'createFromJson'])
                ->setArguments([$config['signature']['key']]);
        }
        $tokenHandlerDefinition->replaceArgument(0, new Reference('security.access_token_handler.oidc.signature'));
        $tokenHandlerDefinition->replaceArgument(1, new Reference('security.access_token_handler.oidc.jwk'));
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
                        ->defaultNull()
                    ->end()
                    ->arrayNode('signature')
                        ->isRequired()
                        ->children()
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
                ->end()
            ->end()
        ;
    }
}
