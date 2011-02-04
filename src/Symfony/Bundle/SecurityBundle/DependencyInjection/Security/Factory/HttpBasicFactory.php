<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\Configuration\Builder\NodeBuilder;

use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * HttpBasicFactory creates services for HTTP basic authentication.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HttpBasicFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $provider = 'security.authentication.provider.dao.'.$id;
        $container
            ->setDefinition($provider, new DefinitionDecorator('security.authentication.provider.dao'))
            ->setArgument(0, new Reference($userProvider))
            ->setArgument(2, $id)
            ->addTag('security.authentication_provider')
        ;

        // listener
        $listenerId = 'security.authentication.listener.basic.'.$id;
        $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.authentication.listener.basic'));
        $listener->setArgument(2, $id);

        if (null === $defaultEntryPoint) {
            $defaultEntryPoint = 'security.authentication.basic_entry_point';
        }

        return array($provider, $listenerId, $defaultEntryPoint);
    }

    public function getPosition()
    {
        return 'http';
    }

    public function getKey()
    {
        return 'http-basic';
    }

    public function addConfiguration(NodeBuilder $builder)
    {
        $builder
            ->scalarNode('provider')->end()
        ;
    }
}
