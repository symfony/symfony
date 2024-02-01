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

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Configures a token handler for an OAuth2 Token Introspection endpoint.
 *
 * @internal
 */
class OAuth2TokenHandlerFactory implements TokenHandlerFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array|string $config): void
    {
        $tokenHandlerDefinition = $container->setDefinition($id, new ChildDefinition('security.access_token_handler.oauth2'));

        // Create the client service
        if (!isset($config['client']['id'])) {
            $clientDefinitionId = 'http_client.security.access_token_handler.oauth2';
            if (!ContainerBuilder::willBeAvailable('symfony/http-client', HttpClient::class, ['symfony/security-bundle'])) {
                $container->register($clientDefinitionId, 'stdClass')
                    ->addError('You cannot use the "oauth2" token handler since the HttpClient component is not installed. Try running "composer require symfony/http-client".');
            } else {
                $container->register($clientDefinitionId, HttpClient::class)
                    ->setFactory([HttpClient::class, 'create'])
                    ->setArguments([$config['client']])
                    ->addTag('http_client.client');
            }
        }

        $tokenHandlerDefinition->replaceArgument(0, new Reference($config['client']['id'] ?? $clientDefinitionId));
    }

    public function getKey(): string
    {
        return 'oauth2';
    }

    public function addConfiguration(NodeBuilder $node): void
    {
        $node
            ->arrayNode($this->getKey())
                ->fixXmlConfig($this->getKey())
                ->children()
                    ->arrayNode('client')
                        ->info('HttpClient to call the Introspection Endpoint.')
                          ->isRequired()
                        ->beforeNormalization()
                            ->ifString()
                           ->then(static function ($v): array { return ['id' => $v]; })
                        ->end()
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
