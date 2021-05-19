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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
class AnonymousFactory implements SecurityFactoryInterface, AuthenticatorFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        if (null === $config['secret']) {
            $config['secret'] = new Parameter('container.build_hash');
        }

        $listenerId = 'security.authentication.listener.anonymous.'.$id;
        $container
            ->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.anonymous'))
            ->replaceArgument(1, $config['secret'])
        ;

        $providerId = 'security.authentication.provider.anonymous.'.$id;
        $container
            ->setDefinition($providerId, new ChildDefinition('security.authentication.provider.anonymous'))
            ->replaceArgument(0, $config['secret'])
        ;

        return [$providerId, $listenerId, $defaultEntryPoint];
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        throw new InvalidConfigurationException(sprintf('The authenticator manager no longer has "anonymous" security. Please remove this option under the "%s" firewall'.($config['lazy'] ? ' and add "lazy: true"' : '').'.', $firewallName));
    }

    public function getPosition()
    {
        return 'anonymous';
    }

    public function getKey()
    {
        return 'anonymous';
    }

    public function addConfiguration(NodeDefinition $builder)
    {
        $builder
            ->beforeNormalization()
                ->ifTrue(function ($v) { return 'lazy' === $v; })
                ->then(function ($v) { return ['lazy' => true]; })
            ->end()
            ->children()
                ->booleanNode('lazy')->defaultFalse()->setDeprecated('symfony/security-bundle', '5.1', 'Using "anonymous: lazy" to make the firewall lazy is deprecated, use "anonymous: true" and "lazy: true" instead.')->end()
                ->scalarNode('secret')->defaultNull()->end()
            ->end()
        ;
    }
}
