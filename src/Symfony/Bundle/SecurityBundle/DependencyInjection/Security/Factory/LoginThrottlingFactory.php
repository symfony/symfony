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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
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
                ->scalarNode('limiter')->info('The name of the limiter that you defined under "framework.rate_limiter".')->end()
                ->integerNode('max_attempts')->defaultValue(5)->end()
            ->end();
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): array
    {
        if (!class_exists(LoginThrottlingListener::class)) {
            throw new \LogicException('Login throttling requires symfony/security-http:^5.2.');
        }

        if (!isset($config['limiter'])) {
            if (!class_exists(FrameworkExtension::class) || !method_exists(FrameworkExtension::class, 'registerRateLimiter')) {
                throw new \LogicException('You must either configure a rate limiter for "security.firewalls.'.$firewallName.'.login_throttling" or install symfony/framework-bundle:^5.2');
            }

            FrameworkExtension::registerRateLimiter($container, $config['limiter'] = '_login_'.$firewallName, [
                'strategy' => 'fixed_window',
                'limit' => $config['max_attempts'],
                'interval' => '1 minute',
                'lock_factory' => 'lock.factory',
                'cache_pool' => 'cache.app',
            ]);
        }

        $container
            ->setDefinition('security.listener.login_throttling.'.$firewallName, new ChildDefinition('security.listener.login_throttling'))
            ->replaceArgument(1, new Reference('limiter.'.$config['limiter']))
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$firewallName]);

        return [];
    }
}
