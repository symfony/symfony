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

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Http\EventListener\LoginThrottlingListener;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class LoginThrottlingFactory implements AuthenticatorFactoryInterface, SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array $config, string $userProvider, ?string $defaultEntryPoint)
    {
        throw new \LogicException('Login throttling is not supported when "security.enable_authenticator_manager" is not set to true.');
    }

    public function getPosition(): string
    {
        // this factory doesn't register any authenticators, this position doesn't matter
        return 'pre_auth';
    }

    public function getKey(): string
    {
        return 'login_throttling';
    }

    /**
     * @param ArrayNodeDefinition $builder
     */
    public function addConfiguration(NodeDefinition $builder)
    {
        $builder
            ->children()
                ->integerNode('threshold')->defaultValue(3)->end()
                ->integerNode('lock_timeout')->defaultValue(1)->end()
            ->end();
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): array
    {
        if (!class_exists(LoginThrottlingListener::class)) {
            throw new \LogicException('Login throttling requires symfony/security-http:^5.2.');
        }

        $container
            ->setDefinition('security.listener.login_throttling.'.$firewallName, new ChildDefinition('security.listener.login_throttling'))
            ->replaceArgument(1, $config['threshold'])
            ->replaceArgument(2, $config['lock_timeout'])
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$firewallName]);

        return [];
    }
}
