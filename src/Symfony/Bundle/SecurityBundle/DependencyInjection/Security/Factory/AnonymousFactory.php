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
use Symfony\Component\DependencyInjection\Parameter;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class AnonymousFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        if (null === $config['secret']) {
            $firewall['anonymous']['secret'] = new Parameter('container.build_hash');
        }

        $listenerId = 'security.authentication.listener.anonymous.'.$id;
        $container
            ->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.anonymous'))
            ->replaceArgument(1, $firewall['anonymous']['secret'])
        ;

        $providerId = 'security.authentication.provider.anonymous.'.$id;
        $container
            ->setDefinition($providerId, new ChildDefinition('security.authentication.provider.anonymous'))
            ->replaceArgument(0, $firewall['anonymous']['secret'])
        ;

        return [$providerId, $listenerId, $defaultEntryPoint];
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
                ->booleanNode('lazy')->defaultFalse()->end()
                ->scalarNode('secret')->defaultNull()->end()
            ->end()
        ;
    }
}
