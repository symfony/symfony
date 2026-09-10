<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires what only another bundle can provide, and drops the services that would be left dangling.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $notifyOnFailedMessages = $container->hasParameter('.notifier.notification_on_failed_messages')
            && $container->getParameter('.notifier.notification_on_failed_messages');
        $container->getParameterBag()->remove('.notifier.notification_on_failed_messages');

        if (!$container->hasDefinition('notifier')) {
            return;
        }

        if ($container->hasAlias('messenger.default_bus')) {
            if ($notifyOnFailedMessages) {
                $container->getDefinition('notifier.failed_message_listener')->addTag('kernel.event_subscriber');
            }

            // as we have a bus, the channels don't need the transports
            foreach (['notifier.channel.chat', 'notifier.channel.email', 'notifier.channel.sms', 'notifier.channel.push', 'notifier.channel.desktop'] as $channelId) {
                if ($container->hasDefinition($channelId)) {
                    $container->getDefinition($channelId)->setArgument(0, null);
                }
            }
        }

        if ($container->hasDefinition('notifier.channel.email')) {
            if ($container->hasDefinition('mailer.envelope_listener')) {
                $sender = $container->getDefinition('mailer.envelope_listener')->getArgument(0);
                $container->getDefinition('notifier.channel.email')->setArgument(2, $sender);
            } else {
                $container->removeDefinition('notifier.channel.email');
            }
        }

        if (!$container->hasDefinition('notifier.notification_logger_listener') || $container->has('test.client')) {
            // the test assertions read the listener directly, so it must keep collecting unconditionally
            return;
        }

        // the listener keeps every one of them for the lifetime of the process, so let it skip
        // the notifications nobody will collect
        $container->getDefinition('notifier.notification_logger_listener')
            ->setArgument(0, new Reference('profiler.is_disabled_state_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE));
    }
}
