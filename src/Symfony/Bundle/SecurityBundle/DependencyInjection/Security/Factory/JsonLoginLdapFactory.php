<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * JsonLoginLdapFactory creates services for json login ldap authentication.
 */
class JsonLoginLdapFactory extends JsonLoginFactory
{
    public function getKey()
    {
        return 'json-login-ldap';
    }

    protected function createAuthProvider(ContainerBuilder $container, $id, $config, $userProviderId)
    {
        $provider = 'security.authentication.provider.ldap_bind.'.$id;
        $definition = $container
            ->setDefinition($provider, new ChildDefinition('security.authentication.provider.ldap_bind'))
            ->replaceArgument(0, new Reference($userProviderId))
            ->replaceArgument(1, new Reference('security.user_checker.'.$id))
            ->replaceArgument(2, $id)
            ->replaceArgument(3, new Reference($config['service']))
            ->replaceArgument(4, $config['dn_string'])
        ;

        if (!empty($config['query_string'])) {
            $definition->addMethodCall('setQueryString', [$config['query_string']]);
        }

        return $provider;
    }

    public function addConfiguration(NodeDefinition $node)
    {
        parent::addConfiguration($node);

        $node
            ->children()
                ->scalarNode('service')->defaultValue('ldap')->end()
                ->scalarNode('dn_string')->defaultValue('{username}')->end()
                ->scalarNode('query_string')->end()
            ->end()
        ;
    }
}
