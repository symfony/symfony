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
use Symfony\Component\DependencyInjection\ChildDefinitionTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * RemoteUserFactory creates services for REMOTE_USER based authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Maxime Douailin <maxime.douailin@gmail.com>
 */
class RemoteUserFactory implements SecurityFactoryInterface
{
    use ChildDefinitionTrait;

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = 'security.authentication.provider.pre_authenticated.'.$id;
        $container
            ->setDefinition($providerId, $this->createChildDefinition('security.authentication.provider.pre_authenticated'))
            ->replaceArgument(0, new Reference($userProvider))
            ->replaceArgument(1, new Reference('security.user_checker.'.$id))
            ->addArgument($id)
        ;

        $listenerId = 'security.authentication.listener.remote_user.'.$id;
        $listener = $container->setDefinition($listenerId, $this->createChildDefinition('security.authentication.listener.remote_user'));
        $listener->replaceArgument(2, $id);
        $listener->replaceArgument(3, $config['user']);

        return array($providerId, $listenerId, $defaultEntryPoint);
    }

    public function getPosition()
    {
        return 'pre_auth';
    }

    public function getKey()
    {
        return 'remote-user';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
                ->scalarNode('provider')->end()
                ->scalarNode('user')->defaultValue('REMOTE_USER')->end()
            ->end()
        ;
    }
}
