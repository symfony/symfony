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

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * LdapFactory creates services for Ldap user provider.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class LdapFactory implements UserProviderFactoryInterface
{
    private $key;
    private $providerId;

    public function create(ContainerBuilder $container, $id, $config)
    {
        $container
            ->setDefinition('security.ldap.ldap.'.$id , new DefinitionDecorator('security.ldap.ldap'))
            ->addArgument($config['host'])
            ->addArgument($config['port'])
            ->addArgument($config['dn'])
            ->addArgument($config['username_suffix'])
            ->addArgument($config['version'])
            ->addArgument($config['use_ssl'])
            ->addArgument($config['use_start_tls'])
            ->addArgument($config['opt_referrals'])
        ;

        $container
            ->setDefinition($id, new DefinitionDecorator('security.user.provider.ldap'))
            ->replaceArgument(0, new Reference('security.ldap.ldap.'.$id))
            ->replaceArgument(1, $config['default_roles'])
        ;
    }

    public function getKey()
    {
        return 'ldap';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
                ->scalarNode('host')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('port')->cannotBeEmpty()->defaultValue(389)->end()
                ->scalarNode('dn')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('username_suffix')->defaultValue('')->end()
                ->scalarNode('version')->defaultValue(3)->end()
                ->scalarNode('use_ssl')->defaultFalse()->end()
                ->scalarNode('use_start_tls')->defaultFalse()->end()
                ->scalarNode('opt_referrals')->defaultFalse()->end()
                ->arrayNode('default_roles')
                    ->beforeNormalization()->ifString()->then(function($v) { return preg_split('/\s*,\s*/', $v); })->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }
}
