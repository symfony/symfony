<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterEventPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $events = [];
        foreach (array_keys($container->findTaggedServiceIds('kernel.event')) as $eventServiceId) {
            $events[$container->getDefinition($eventServiceId)->getClass()] = $eventServiceId;
        }

        $container->setParameter('kernel.events', array_merge(
            $container->getParameter('kernel.events'),
            array_flip($events)
        ));
    }
}
