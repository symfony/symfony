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
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Http\EventListener\RememberMeLogoutListener;

/**
 * @internal
 */
class RememberMeFactory implements SecurityFactoryInterface, AuthenticatorFactoryInterface
{
    protected $options = [
        'name' => 'REMEMBERME',
        'lifetime' => 31536000,
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'httponly' => true,
        'samesite' => null,
        'always_remember_me' => false,
        'remember_me_parameter' => '_remember_me',
    ];

    public function create(ContainerBuilder $container, string $id, array $config, ?string $userProvider, ?string $defaultEntryPoint)
    {
        // authentication provider
        $authProviderId = 'security.authentication.provider.rememberme.'.$id;
        $container
            ->setDefinition($authProviderId, new ChildDefinition('security.authentication.provider.rememberme'))
            ->replaceArgument(0, new Reference('security.user_checker.'.$id))
            ->addArgument($config['secret'])
            ->addArgument($id)
        ;

        // remember me services
        $templateId = $this->generateRememberMeServicesTemplateId($config, $id);
        $rememberMeServicesId = $templateId.'.'.$id;

        // attach to remember-me aware listeners
        $userProviders = [];
        foreach ($container->findTaggedServiceIds('security.remember_me_aware') as $serviceId => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['id']) || $attribute['id'] !== $id) {
                    continue;
                }

                if (!isset($attribute['provider'])) {
                    throw new \RuntimeException('Each "security.remember_me_aware" tag must have a provider attribute.');
                }

                // context listeners don't need a provider
                if ('none' !== $attribute['provider']) {
                    $userProviders[] = new Reference($attribute['provider']);
                }

                $container
                    ->getDefinition($serviceId)
                    ->addMethodCall('setRememberMeServices', [new Reference($rememberMeServicesId)])
                ;
            }
        }

        $this->createRememberMeServices($container, $id, $templateId, $userProviders, $config);

        // remember-me listener
        $listenerId = 'security.authentication.listener.rememberme.'.$id;
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.rememberme'));
        $listener->replaceArgument(1, new Reference($rememberMeServicesId));
        $listener->replaceArgument(5, $config['catch_exceptions']);

        // remember-me logout listener
        $container->setDefinition('security.logout.listener.remember_me.'.$id, new Definition(RememberMeLogoutListener::class))
            ->addArgument(new Reference($rememberMeServicesId))
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$id]);

        return [$authProviderId, $listenerId, $defaultEntryPoint];
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $templateId = $this->generateRememberMeServicesTemplateId($config, $firewallName);
        $rememberMeServicesId = $templateId.'.'.$firewallName;

        // create remember me services (which manage the remember me cookies)
        $this->createRememberMeServices($container, $firewallName, $templateId, [new Reference($userProviderId)], $config);

        // create remember me listener (which executes the remember me services for other authenticators and logout)
        $this->createRememberMeListener($container, $firewallName, $rememberMeServicesId);

        // create remember me authenticator (which re-authenticates the user based on the remember me cookie)
        $authenticatorId = 'security.authenticator.remember_me.'.$firewallName;
        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.remember_me'))
            ->replaceArgument(0, new Reference($rememberMeServicesId))
            ->replaceArgument(3, $container->getDefinition($rememberMeServicesId)->getArgument(3))
        ;

        foreach ($container->findTaggedServiceIds('security.remember_me_aware') as $serviceId => $attributes) {
            // register ContextListener
            if ('security.context_listener' === substr($serviceId, 0, 25)) {
                $container
                    ->getDefinition($serviceId)
                    ->addMethodCall('setRememberMeServices', [new Reference($rememberMeServicesId)])
                ;

                continue;
            }

            throw new \LogicException(sprintf('Symfony Authenticator Security dropped support for the "security.remember_me_aware" tag, service "%s" will no longer work as expected.', $serviceId));
        }

        return $authenticatorId;
    }

    public function getPosition()
    {
        return 'remember_me';
    }

    public function getKey()
    {
        return 'remember-me';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $builder = $node
            ->fixXmlConfig('user_provider')
            ->children()
        ;

        $builder
            ->scalarNode('secret')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('service')->end()
            ->scalarNode('token_provider')->end()
            ->arrayNode('user_providers')
                ->beforeNormalization()
                    ->ifString()->then(function ($v) { return [$v]; })
                ->end()
                ->prototype('scalar')->end()
            ->end()
            ->booleanNode('catch_exceptions')->defaultTrue()->end()
        ;

        foreach ($this->options as $name => $value) {
            if ('secure' === $name) {
                $builder->enumNode($name)->values([true, false, 'auto'])->defaultValue('auto' === $value ? null : $value);
            } elseif ('samesite' === $name) {
                $builder->enumNode($name)->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE])->defaultValue($value);
            } elseif (\is_bool($value)) {
                $builder->booleanNode($name)->defaultValue($value);
            } elseif (\is_int($value)) {
                $builder->integerNode($name)->defaultValue($value);
            } else {
                $builder->scalarNode($name)->defaultValue($value);
            }
        }
    }

    private function generateRememberMeServicesTemplateId(array $config, string $id): string
    {
        if (isset($config['service'])) {
            return $config['service'];
        }

        if (isset($config['token_provider'])) {
            return 'security.authentication.rememberme.services.persistent';
        }

        return 'security.authentication.rememberme.services.simplehash';
    }

    private function createRememberMeServices(ContainerBuilder $container, string $id, string $templateId, array $userProviders, array $config): void
    {
        $rememberMeServicesId = $templateId.'.'.$id;

        $rememberMeServices = $container->setDefinition($rememberMeServicesId, new ChildDefinition($templateId));
        $rememberMeServices->replaceArgument(1, $config['secret']);
        $rememberMeServices->replaceArgument(2, $id);

        if (isset($config['token_provider'])) {
            $rememberMeServices->addMethodCall('setTokenProvider', [
                new Reference($config['token_provider']),
            ]);
        }

        // remember-me options
        $mergedOptions = array_intersect_key($config, $this->options);
        if ('auto' === $mergedOptions['secure']) {
            $mergedOptions['secure'] = null;
        }

        $rememberMeServices->replaceArgument(3, $mergedOptions);

        if ($config['user_providers']) {
            $userProviders = [];
            foreach ($config['user_providers'] as $providerName) {
                $userProviders[] = new Reference('security.user.provider.concrete.'.$providerName);
            }
        }

        if (0 === \count($userProviders)) {
            throw new \RuntimeException('You must configure at least one remember-me aware listener (such as form-login) for each firewall that has remember-me enabled.');
        }

        $rememberMeServices->replaceArgument(0, new IteratorArgument(array_unique($userProviders)));
    }

    private function createRememberMeListener(ContainerBuilder $container, string $id, string $rememberMeServicesId): void
    {
        $container
            ->setDefinition('security.listener.remember_me.'.$id, new ChildDefinition('security.listener.remember_me'))
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$id])
            ->replaceArgument(0, new Reference($rememberMeServicesId))
        ;

        $container
            ->setDefinition('security.logout.listener.remember_me.'.$id, new Definition(RememberMeLogoutListener::class))
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$id])
            ->addArgument(new Reference($rememberMeServicesId));
    }
}
