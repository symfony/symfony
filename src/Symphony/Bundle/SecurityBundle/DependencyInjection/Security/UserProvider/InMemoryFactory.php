<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider;

use Symphony\Component\Config\Definition\Builder\NodeDefinition;
use Symphony\Component\DependencyInjection\ChildDefinition;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Parameter;

/**
 * InMemoryFactory creates services for the memory provider.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class InMemoryFactory implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config)
    {
        $definition = $container->setDefinition($id, new ChildDefinition('security.user.provider.in_memory'));
        $defaultPassword = new Parameter('container.build_id');
        $users = array();

        foreach ($config['users'] as $username => $user) {
            $users[$username] = array('password' => null !== $user['password'] ? (string) $user['password'] : $defaultPassword, 'roles' => $user['roles']);
        }

        $definition->addArgument($users);
    }

    public function getKey()
    {
        return 'memory';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->fixXmlConfig('user')
            ->children()
                ->arrayNode('users')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->prototype('array')
                        ->children()
                            ->scalarNode('password')->defaultNull()->end()
                            ->arrayNode('roles')
                                ->beforeNormalization()->ifString()->then(function ($v) { return preg_split('/\s*,\s*/', $v); })->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
