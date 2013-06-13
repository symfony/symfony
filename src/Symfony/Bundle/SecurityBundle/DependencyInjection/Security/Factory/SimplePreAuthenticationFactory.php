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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class SimplePreAuthenticationFactory implements SecurityFactoryInterface
{
    public function getPosition()
    {
        return 'pre_auth';
    }

    public function getKey()
    {
        return 'simple-preauth';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
                ->scalarNode('provider')->end()
                ->scalarNode('authenticator')->cannotBeEmpty()->end()
            ->end()
        ;
    }

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $provider = 'security.authentication.provider.simple_preauth.'.$id;
        $container
            ->setDefinition($provider, new DefinitionDecorator('security.authentication.provider.simple'))
            ->replaceArgument(0, new Reference($config['authenticator']))
            ->replaceArgument(1, new Reference($userProvider))
            ->replaceArgument(2, $id)
        ;

        // listener
        $listenerId = 'security.authentication.listener.simple_preauth.'.$id;
        $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.authentication.listener.simple_preauth'));
        $listener->replaceArgument(2, $id);
        $listener->replaceArgument(3, new Reference($config['authenticator']));

        return array($provider, $listenerId, null);
    }
}
