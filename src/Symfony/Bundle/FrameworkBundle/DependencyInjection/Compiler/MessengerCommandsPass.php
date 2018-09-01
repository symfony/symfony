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
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class MessengerCommandsPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('console.command.messenger_consume_messages')) {
            return;
        }

        $buses = array();
        foreach ($container->findTaggedServiceIds('messenger.bus') as $busId => $tags) {
            $buses[$busId] = new Reference($busId);
        }

        $container
            ->getDefinition('console.command.messenger_consume_messages')
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $buses))
            ->replaceArgument(3, $this->findReceiverNames($container))
            ->replaceArgument(4, array_keys($buses))
        ;
    }

    private function findReceiverNames(ContainerBuilder $container)
    {
        $receiverNames = array();
        foreach (MessengerPass::findReceivers($container, 'messenger.receiver') as $name => $reference) {
            $receiverNames[(string) $reference] = $name;
        }

        return array_values($receiverNames);
    }
}
