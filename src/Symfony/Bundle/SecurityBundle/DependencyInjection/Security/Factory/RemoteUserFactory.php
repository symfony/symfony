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
 * RemoteUserFactory creates services for REMOTE_USER based authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Maxime Douailin <maxime.douailin@gmail.com>
 *
 * @internal
 */
class RemoteUserFactory implements AuthenticatorFactoryInterface
{
    public const PRIORITY = -10;

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $authenticatorId = 'security.authenticator.remote_user.'.$firewallName;
        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.remote_user'))
            ->replaceArgument(0, new Reference($userProviderId))
            ->replaceArgument(2, $firewallName)
            ->replaceArgument(3, $config['user'])
        ;

        return $authenticatorId;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getKey(): string
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
