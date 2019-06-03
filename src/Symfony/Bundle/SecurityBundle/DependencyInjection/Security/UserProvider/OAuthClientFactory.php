<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class OAuthClientFactory implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config)
    {
        $container
            ->setDefinition($id, new ChildDefinition('security.user.provider.oauth'))
        ;
    }

    public function getKey()
    {
        return 'oauth';
    }

    public function addConfiguration(NodeDefinition $builder)
    {
        $builder
            ->children()
                ->enumNode('type')
                    ->info('The type of OAuth client needed: authorization_code, implicit, client_credentials, resource_owner')
                    ->values(['authorization_code', 'implicit', 'client_credentials', 'resource_owner'])
                        ->isRequired()
                        ->cannotBeEmpty()
                ->end()
                ->scalarNode('client_id')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->defaultValue('12345678')
                ->end()
                ->scalarNode('client_secret')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->defaultValue('12345678')
                ->end()
                ->scalarNode('authorization_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->defaultValue('https://foo.com/authenticate')
                ->end()
                ->scalarNode('redirect_uri')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->defaultValue('https://myapp.com/oauth')
                ->end()
                ->scalarNode('access_token_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->defaultValue('https://foo.com/token')
                ->end()
            ->end()
        ;
    }
}
