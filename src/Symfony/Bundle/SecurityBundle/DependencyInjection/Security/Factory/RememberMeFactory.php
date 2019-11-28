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

class RememberMeFactory implements SecurityFactoryInterface
{
    protected $options = [
        'name' => 'REMEMBERME',
        'lifetime' => 31536000,
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'httponly' => true,
        'always_remember_me' => false,
        'remember_me_parameter' => '_remember_me',
    ];

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
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
        if (isset($config['token_provider'])) {
            $templateId = 'security.authentication.rememberme.services.persistent';
            $rememberMeServicesId = $templateId.'.'.$id;
        } else {
            $templateId = 'security.authentication.rememberme.services.simplehash';
            $rememberMeServicesId = $templateId.'.'.$id;
        }

        if ($container->hasDefinition('security.logout_listener.'.$id)) {
            $container
                ->getDefinition('security.logout_listener.'.$id)
                ->addMethodCall('addHandler', [new Reference($rememberMeServicesId)])
            ;
        }

        $rememberMeServices = $container->setDefinition($rememberMeServicesId, new ChildDefinition($templateId));
        $rememberMeServices->replaceArgument(1, $config['secret']);
        $rememberMeServices->replaceArgument(2, $id);

        if (isset($config['token_provider'])) {
            $rememberMeServices->addMethodCall('setTokenProvider', [
                new Reference($config['token_provider']),
            ]);
        }

        // remember-me options
        $rememberMeServices->replaceArgument(3, array_intersect_key($config, $this->options));

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
        if ($config['user_providers']) {
            $userProviders = [];
            foreach ($config['user_providers'] as $providerName) {
                $userProviders[] = new Reference('security.user.provider.concrete.'.$providerName);
            }
        }
        if (0 === \count($userProviders)) {
            throw new \RuntimeException('You must configure at least one remember-me aware listener (such as form-login) for each firewall that has remember-me enabled.');
        }

        $rememberMeServices->replaceArgument(0, array_unique($userProviders));

        // remember-me listener
        $listenerId = 'security.authentication.listener.rememberme.'.$id;
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.rememberme'));
        $listener->replaceArgument(1, new Reference($rememberMeServicesId));
        $listener->replaceArgument(5, $config['catch_exceptions']);

        return [$authProviderId, $listenerId, $defaultEntryPoint];
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
            ->scalarNode('token_provider')->end()
            ->arrayNode('user_providers')
                ->beforeNormalization()
                    ->ifString()->then(function ($v) { return [$v]; })
                ->end()
                ->prototype('scalar')->end()
            ->end()
            ->scalarNode('catch_exceptions')->defaultTrue()->end()
        ;

        foreach ($this->options as $name => $value) {
            if (\is_bool($value)) {
                $builder->booleanNode($name)->defaultValue($value);
            } else {
                $builder->scalarNode($name)->defaultValue($value);
            }
        }
    }
}
