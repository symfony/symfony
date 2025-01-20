<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\DependencyInjection;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Scheduler\Messenger\ServiceCallMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;

/**
 * @internal
 */
class AddScheduleMessengerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('event_dispatcher')) {
            $container->removeDefinition('scheduler.event_listener');
        }

        $receivers = [];
        foreach ($container->findTaggedServiceIds('messenger.receiver') as $serviceId => $tags) {
            $receivers[$serviceId] = true;
            if (isset($tags[0]['alias'])) {
                $receivers[$tags[0]['alias']] = true;
            }
        }

        $scheduleProviderIds = [];
        foreach ($container->findTaggedServiceIds('scheduler.schedule_provider') as $serviceId => $tags) {
            $name = $tags[0]['name'];
            $scheduleProviderIds[$name] = $serviceId;
        }

        $tasksPerSchedule = [];
        foreach ($container->findTaggedServiceIds('scheduler.task') as $serviceId => $tags) {
            foreach ($tags as $tagAttributes) {
                $serviceDefinition = $container->getDefinition($serviceId);
                $scheduleName = $tagAttributes['schedule'] ?? 'default';

                if ($serviceDefinition->hasTag('console.command')) {
                    $message = new Definition(RunCommandMessage::class, [$serviceDefinition->getClass()::getDefaultName().(empty($tagAttributes['arguments']) ? '' : " {$tagAttributes['arguments']}")]);
                } else {
                    $message = new Definition(ServiceCallMessage::class, [$serviceId, $tagAttributes['method'] ?? '__invoke', (array) ($tagAttributes['arguments'] ?? [])]);
                }

                if ($tagAttributes['transports'] ?? null) {
                    $message = new Definition(RedispatchMessage::class, [$message, $tagAttributes['transports']]);
                }

                $taskArguments = [
                    '$message' => $message,
                ] + array_filter(match ($tagAttributes['trigger'] ?? throw new InvalidArgumentException(\sprintf('Tag "scheduler.task" is missing attribute "trigger" on service "%s".', $serviceId))) {
                    'every' => [
                        '$frequency' => $tagAttributes['frequency'] ?? throw new InvalidArgumentException(\sprintf('Tag "scheduler.task" is missing attribute "frequency" on service "%s".', $serviceId)),
                        '$from' => $tagAttributes['from'] ?? null,
                        '$until' => $tagAttributes['until'] ?? null,
                    ],
                    'cron' => [
                        '$expression' => $tagAttributes['expression'] ?? throw new InvalidArgumentException(\sprintf('Tag "scheduler.task" is missing attribute "expression" on service "%s".', $serviceId)),
                        '$timezone' => $tagAttributes['timezone'] ?? null,
                    ],
                }, fn ($value) => null !== $value);

                $tasksPerSchedule[$scheduleName][] = $taskDefinition = (new Definition(RecurringMessage::class))
                    ->setFactory([RecurringMessage::class, $tagAttributes['trigger']])
                    ->setArguments($taskArguments);

                if ($tagAttributes['jitter'] ?? false) {
                    $taskDefinition->addMethodCall('withJitter', [$tagAttributes['jitter']], true);
                }
            }
        }

        foreach ($tasksPerSchedule as $scheduleName => $tasks) {
            $id = "scheduler.provider.$scheduleName";
            $schedule = (new Definition(Schedule::class))->addMethodCall('add', $tasks);

            if (isset($scheduleProviderIds[$scheduleName])) {
                $schedule
                    ->setFactory([new Reference('.inner'), 'getSchedule'])
                    ->setDecoratedService($scheduleProviderIds[$scheduleName]);
            } else {
                $schedule->addTag('scheduler.schedule_provider', ['name' => $scheduleName]);
                $scheduleProviderIds[$scheduleName] = $id;
            }

            $container->setDefinition($id, $schedule);
        }

        foreach (array_keys($scheduleProviderIds) as $name) {
            $transportName = 'scheduler_'.$name;

            // allows to override the default transport registration
            // in case one needs to configure it further (like choosing a different serializer)
            if (isset($receivers[$transportName])) {
                continue;
            }

            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments(['schedule://'.$name, ['transport_name' => $transportName], new Reference('messenger.default_serializer')])
                ->addTag('messenger.receiver', ['alias' => $transportName])
            ;
            $container->setDefinition('messenger.transport.'.$transportName, $transportDefinition);
        }
    }
}
