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
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class AnonymousAuthenticationFactory implements SecurityFactoryInterface
{
    public function getPosition()
    {
        return 'anon';
    }

    public function getKey()
    {
        return 'anonymous';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
            ->scalarNode('secret')->defaultValue(uniqid('', true))->end()
            ->end()
            ->end()
        ;
    }

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $listenerId = 'security.authentication.listener.anonymous.'.$id;
        $container
            ->setDefinition($listenerId, new DefinitionDecorator('security.authentication.listener.anonymous'))
            ->replaceArgument(1, $config['secret'])
        ;

        $providerId = 'security.authentication.provider.anonymous.'.$id;
        $container
            ->setDefinition($providerId, new DefinitionDecorator('security.authentication.provider.anonymous'))
            ->replaceArgument(0, $config['secret'])
        ;

        return array($providerId, $listenerId, null);
    }
}
