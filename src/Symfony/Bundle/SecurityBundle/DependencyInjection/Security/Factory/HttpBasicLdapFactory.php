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

use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * HttpBasicFactory creates services for HTTP basic authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class HttpBasicLdapFactory extends HttpBasicFactory
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $provider = 'security.authentication.provider.ldap.'.$id;
        $container
            ->setDefinition($provider, new DefinitionDecorator('security.authentication.provider.ldap'))
            ->replaceArgument(0, new Reference($userProvider))
            ->replaceArgument(2, $id)
        ;

        // entry point
        $entryPointId = $this->createEntryPoint($container, $id, $config, $defaultEntryPoint);

        // listener
        $listenerId = 'security.authentication.listener.basic.'.$id;
        $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.authentication.listener.basic'));
        $listener->replaceArgument(2, $id);
        $listener->replaceArgument(3, new Reference($entryPointId));

        return array($provider, $listenerId, $entryPointId);
    }

    public function getKey()
    {
        return 'http-basic-ldap';
    }
}
