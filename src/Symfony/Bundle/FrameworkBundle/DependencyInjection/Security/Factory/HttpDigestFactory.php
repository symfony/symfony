<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * HttpDigestFactory creates services for HTTP digest authentication.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HttpDigestFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $provider = 'security.authentication.provider.dao.'.$id;
        $container
            ->register($provider, '%security.authentication.provider.dao.class%')
            ->setArguments(array(new Reference($userProvider), new Reference('security.account_checker'), new Reference('security.encoder_factory')))
            ->setPublic(false)
        ;

        // listener
        $listenerId = 'security.authentication.listener.digest.'.$id;
        $listener = $container->setDefinition($listenerId, clone $container->getDefinition('security.authentication.listener.digest'));
        $arguments = $listener->getArguments();
        $arguments[1] = new Reference($userProvider);
        $listener->setArguments($arguments);

        if (null === $defaultEntryPoint) {
            $defaultEntryPoint = 'security.authentication.digest_entry_point';
        }

        return array($provider, $listenerId, $defaultEntryPoint);
    }

    public function getPosition()
    {
        return 'http';
    }

    public function getKey()
    {
        return 'http-digest';
    }
}
