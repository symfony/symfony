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
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Configures a token handler for an OIDC server.
 */
class OidcUserInfoTokenHandlerFactory implements TokenHandlerFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array|string $config): void
    {
        $clientDefinition = (new ChildDefinition('security.access_token_handler.oidc_user_info.http_client'))
            ->replaceArgument(0, ['base_uri' => $config['base_uri']]);

        if (isset($config['client'])) {
            $clientDefinition->setFactory([new Reference($config['client']), 'withOptions']);
        } elseif (!ContainerBuilder::willBeAvailable('symfony/http-client', HttpClientInterface::class, ['symfony/security-bundle'])) {
            throw new LogicException('You cannot use the "oidc_user_info" token handler since the HttpClient component is not installed. Try running "composer require symfony/http-client".');
        }

        $container->setDefinition($id, new ChildDefinition('security.access_token_handler.oidc_user_info'))
            ->replaceArgument(0, $clientDefinition)
            ->replaceArgument(2, $config['claim']);
    }

    public function getKey(): string
    {
        return 'oidc_user_info';
    }

    public function addConfiguration(NodeBuilder $node): void
    {
        $node
            ->arrayNode($this->getKey())
                ->fixXmlConfig($this->getKey())
                ->beforeNormalization()
                    ->ifString()
                    ->then(fn ($v) => ['claim' => 'sub', 'base_uri' => $v])
                ->end()
                ->children()
                    ->scalarNode('base_uri')
                        ->info('Base URI of the userinfo endpoint on the OIDC server.')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('claim')
                        ->info('Claim which contains the user identifier (e.g. sub, email, etc.).')
                        ->defaultValue('sub')
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('client')
                        ->info('HttpClient service id to use to call the OIDC server.')
                    ->end()
                ->end()
            ->end()
        ;
    }
}
