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
 * X509Factory creates services for X509 certificate authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class X509Factory implements SecurityFactoryInterface, AuthenticatorFactoryInterface
{
    public const PRIORITY = -10;

    public function create(ContainerBuilder $container, string $id, array $config, string $userProvider, ?string $defaultEntryPoint): array
    {
        $providerId = 'security.authentication.provider.pre_authenticated.'.$id;
        $container
            ->setDefinition($providerId, new ChildDefinition('security.authentication.provider.pre_authenticated'))
            ->replaceArgument(0, new Reference($userProvider))
            ->replaceArgument(1, new Reference('security.user_checker.'.$id))
            ->addArgument($id)
        ;

        // listener
        $listenerId = 'security.authentication.listener.x509.'.$id;
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.x509'));
        $listener->replaceArgument(2, $id);
        $listener->replaceArgument(3, $config['user']);
        $listener->replaceArgument(4, $config['credentials']);
        $listener->addMethodCall('setSessionAuthenticationStrategy', [new Reference('security.authentication.session_strategy.'.$id)]);

        return [$providerId, $listenerId, $defaultEntryPoint];
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId)
    {
        $authenticatorId = 'security.authenticator.x509.'.$firewallName;
        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.x509'))
            ->replaceArgument(0, new Reference($userProviderId))
            ->replaceArgument(2, $firewallName)
            ->replaceArgument(3, $config['user'])
            ->replaceArgument(4, $config['credentials'])
        ;

        return $authenticatorId;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getPosition(): string
    {
        return 'pre_auth';
    }

    public function getKey(): string
    {
        return 'x509';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
                ->scalarNode('provider')->end()
                ->scalarNode('user')->defaultValue('SSL_CLIENT_S_DN_Email')->end()
                ->scalarNode('credentials')->defaultValue('SSL_CLIENT_S_DN')->end()
            ->end()
        ;
    }
}
