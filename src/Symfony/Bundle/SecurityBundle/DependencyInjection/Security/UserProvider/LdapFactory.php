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
use Symfony\Component\DependencyInjection\Reference;

/**
 * LdapFactory creates services for Ldap user provider.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class LdapFactory implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array $config): void
    {
        $container
            ->setDefinition($id, new ChildDefinition('security.user.provider.ldap'))
            ->replaceArgument(0, new Reference($config['service']))
            ->replaceArgument(1, $config['base_dn'])
            ->replaceArgument(2, $config['search_dn'])
            ->replaceArgument(3, $config['search_password'])
            ->replaceArgument(4, $config['default_roles'])
            ->replaceArgument(5, $config['uid_key'])
            ->replaceArgument(6, $config['filter'])
            ->replaceArgument(7, $config['password_attribute'])
            ->replaceArgument(8, $config['extra_fields'])
        ;
    }

    public function getKey(): string
    {
        return 'ldap';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $node
            ->fixXmlConfig('extra_field')
            ->fixXmlConfig('default_role')
            ->children()
                ->scalarNode('service')->isRequired()->cannotBeEmpty()->defaultValue('ldap')->end()
                ->scalarNode('base_dn')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('search_dn')->defaultNull()->end()
                ->scalarNode('search_password')->defaultNull()->end()
                ->arrayNode('extra_fields')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('default_roles')
                    ->beforeNormalization()->ifString()->then(fn ($v) => preg_split('/\s*,\s*/', $v))->end()
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('uid_key')->defaultValue('sAMAccountName')->end()
                ->scalarNode('filter')->defaultValue('({uid_key}={user_identifier})')->end()
                ->scalarNode('password_attribute')->defaultNull()->end()
            ->end()
        ;
    }
}
