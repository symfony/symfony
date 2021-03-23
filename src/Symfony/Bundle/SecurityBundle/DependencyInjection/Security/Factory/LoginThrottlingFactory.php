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
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\EventListener\LoginThrottlingListener;
use Symfony\Component\Security\Http\RateLimiter\DefaultLoginRateLimiter;

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
                ->scalarNode('limiter')->info(sprintf('A service id implementing "%s".', RequestRateLimiterInterface::class))->end()
                ->integerNode('max_attempts')->defaultValue(5)->end()
                ->scalarNode('interval')->defaultValue('1 minute')->end()
                ->scalarNode('lock_factory')->info('The service ID of the lock factory used by the login rate limiter (or null to disable locking)')->defaultNull()->end()
            ->end();
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): array
    {
        if (!class_exists(LoginThrottlingListener::class)) {
            throw new \LogicException('Login throttling requires symfony/security-http:^5.2.');
        }

        if (!class_exists(RateLimiterFactory::class)) {
            throw new \LogicException('Login throttling requires the Rate Limiter component. Try running "composer require symfony/rate-limiter".');
        }

        if (!isset($config['limiter'])) {
            if (!class_exists(FrameworkExtension::class) || !method_exists(FrameworkExtension::class, 'registerRateLimiter')) {
                throw new \LogicException('You must either configure a rate limiter for "security.firewalls.'.$firewallName.'.login_throttling" or install symfony/framework-bundle:^5.2.');
            }

            $limiterOptions = [
                'policy' => 'fixed_window',
                'limit' => $config['max_attempts'],
                'interval' => $config['interval'],
                'lock_factory' => $config['lock_factory'],
            ];
            FrameworkExtension::registerRateLimiter($container, $localId = '_login_local_'.$firewallName, $limiterOptions);

            $limiterOptions['limit'] = 5 * $config['max_attempts'];
            FrameworkExtension::registerRateLimiter($container, $globalId = '_login_global_'.$firewallName, $limiterOptions);

            $container->register($config['limiter'] = 'security.login_throttling.'.$firewallName.'.limiter', DefaultLoginRateLimiter::class)
                ->addArgument(new Reference('limiter.'.$globalId))
                ->addArgument(new Reference('limiter.'.$localId))
            ;
        }

        $container
            ->setDefinition('security.listener.login_throttling.'.$firewallName, new ChildDefinition('security.listener.login_throttling'))
            ->replaceArgument(1, new Reference($config['limiter']))
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$firewallName]);

        return [];
    }
}
