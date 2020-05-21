<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Bag\BagRegistry;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Cron\CronFactory;
use Symfony\Component\Scheduler\Cron\CronGenerator;
use Symfony\Component\Scheduler\Cron\CronRegistry;
use Symfony\Component\Scheduler\EventListener\MailerBagSubscriber;
use Symfony\Component\Scheduler\EventListener\MercureBagSubscriber;
use Symfony\Component\Scheduler\EventListener\NotifierBagSubscriber;
use Symfony\Component\Scheduler\EventListener\TaskSubscriber;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\ExecutionModeOrchestratorInterface;
use Symfony\Component\Scheduler\Export\Exporter;
use Symfony\Component\Scheduler\Export\ExporterInterface;
use Symfony\Component\Scheduler\Export\SerializerFormatter;
use Symfony\Component\Scheduler\Expression\ExpressionFactory;
use Symfony\Component\Scheduler\Messenger\TaskMessageHandler;
use Symfony\Component\Scheduler\Runner\CallBackTaskRunner;
use Symfony\Component\Scheduler\Runner\CommandTaskRunner;
use Symfony\Component\Scheduler\Runner\HttpTaskRunner;
use Symfony\Component\Scheduler\Runner\MessengerTaskRunner;
use Symfony\Component\Scheduler\Runner\NotificationTaskRunner;
use Symfony\Component\Scheduler\Runner\NullTaskRunner;
use Symfony\Component\Scheduler\Runner\ShellTaskRunner;
use Symfony\Component\Scheduler\SchedulerRegistry;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Scheduler\Task\CommandFactory;
use Symfony\Component\Scheduler\Task\HttpTaskFactory;
use Symfony\Component\Scheduler\Task\NullFactory;
use Symfony\Component\Scheduler\Task\ShellFactory;
use Symfony\Component\Scheduler\Task\TaskExecutionWatcher;
use Symfony\Component\Scheduler\Task\TaskExecutionWatcherInterface;
use Symfony\Component\Scheduler\Task\TaskFactory;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Transport\LocalTransportFactory;
use Symfony\Component\Scheduler\Transport\NullTransportFactory;
use Symfony\Component\Scheduler\Transport\TransportFactory;
use Symfony\Component\Scheduler\Worker\Worker as SchedulerWorker;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function(ContainerConfigurator $container): void {
    $container->services()
        ->set('scheduler.cron.registry', CronRegistry::class)
        ->set('scheduler.cron.factory', CronFactory::class)
        ->args([
            service('scheduler.cron.registry'),
        ])

        ->set('scheduler.bag.registry', BagRegistry::class)
        ->alias(BagRegistryInterface::class, 'scheduler.bag.registry')

        ->set('scheduler.registry', SchedulerRegistry::class)
        ->args([
            tagged_iterator('scheduler.hub_subscriber'),
        ])
        ->alias(SchedulerRegistryInterface::class, 'scheduler.registry')

        ->set('scheduler.application', Application::class)
        ->args([
            service('kernel'),
        ])

        // Exporter & formatters
        ->set('scheduler.exporter', Exporter::class)
        ->args([
            tagged_iterator('scheduler.exporter_formatter'),
            service('filesystem'),
            service('logger')->nullOnInvalid(),
        ])
        ->alias(ExporterInterface::class, 'scheduler.exporter')

        ->set('scheduler.exporter.serializer_formatter', SerializerFormatter::class)
        ->args([
            service('serializer'),
        ])
        ->tag('scheduler.exporter_formatter')

        // Transports factories
        ->set('scheduler.transport_factory', TransportFactory::class)
        ->args([
            tagged_iterator('scheduler.transport_factory'),
        ])
        ->set('scheduler.transport_factory.local', LocalTransportFactory::class)
        ->tag('scheduler.transport_factory')
        ->set('scheduler.transport_factory.null', NullTransportFactory::class)
        ->tag('scheduler.transport_factory')

        ->set('scheduler.cron_generator', CronGenerator::class)
        ->args([
            service('filesystem'),
            service('logger')->nullOnInvalid(),
        ])

        ->set('scheduler.expression_factory', ExpressionFactory::class)

        ->set('scheduler.execution_mode_orchestrator', ExecutionModeOrchestrator::class)
        ->alias(ExecutionModeOrchestratorInterface::class, 'scheduler.execution_mode_orchestrator')

        // Task Factories
        ->set('scheduler.shell_task.factory', ShellFactory::class)
        ->tag('scheduler.task_factory')

        ->set('scheduler.command_task.factory', CommandFactory::class)
        ->tag('scheduler.task_factory')

        ->set('scheduler.http_task.factory', HttpTaskFactory::class)
        ->tag('scheduler.task_factory')

        ->set('scheduler.null_task.factory', NullFactory::class)
        ->tag('scheduler.task_factory')

        ->set('scheduler.task_factory', TaskFactory::class)
        ->args([
            tagged_iterator('scheduler.task_factory')
        ])
        ->alias(TaskFactoryInterface::class, 'scheduler.task_factory')

        // Runners
        ->set('scheduler.shell_runner', ShellTaskRunner::class)
        ->tag('scheduler.runner')

        ->set('scheduler.command_runner', CommandTaskRunner::class)
        ->args([
            service('scheduler.application'),
        ])
        ->tag('scheduler.runner')

        ->set('scheduler.callback_runner', CallBackTaskRunner::class)
        ->tag('scheduler.runner')

        ->set('scheduler.http_runner', HttpTaskRunner::class)
        ->args([
            service('http_client')->nullOnInvalid()
        ])
        ->tag('scheduler.runner')

        ->set('scheduler.messenger_runner', MessengerTaskRunner::class)
        ->args([
            service(MessageBusInterface::class)->nullOnInvalid(),
        ])
        ->tag('scheduler.runner')

        ->set('scheduler.notifier_runner', NotificationTaskRunner::class)
        ->args([
            service('notifier')->nullOnInvalid(),
        ])
        ->tag('scheduler.runner')

        ->set('scheduler.null_runner', NullTaskRunner::class)
        ->tag('scheduler.runner')

        // Serializer
        ->set('scheduler.normalizer', TaskNormalizer::class)
        ->tag('serializer.normalizer')

        // Messenger
        ->set('scheduler.task_message.handler', TaskMessageHandler::class)
        ->args([
            service('scheduler.worker'),
        ])
        ->tag('messenger.message_handler')

        // Subscribers
        ->set('scheduler.mailer_subscriber', MailerBagSubscriber::class)
        ->args([
            service('scheduler.bag_registry'),
            service('mailer.mailer')->nullOnInvalid(),
        ])
        ->tag('kernel.event_subscriber')

        ->set('scheduler.messenger_subscriber', MailerBagSubscriber::class)
        ->args([
            service('scheduler.bag_registry'),
            service(MessageBusInterface::class)->nullOnInvalid(),
        ])
        ->tag('kernel.event_subscriber')

        ->set('scheduler.mercure_subscriber', MercureBagSubscriber::class)
        ->args([
            service('scheduler.bag_registry'),
            service(Publisher::class)->nullOnInvalid(),
        ])
        ->tag('kernel.event_subscriber')

        ->set('scheduler.notifier_subscriber', NotifierBagSubscriber::class)
        ->args([
            service('scheduler.bag_registry'),
            service('notifier')->nullOnInvalid(),
        ])
        ->tag('kernel.event_subscriber')

        ->set('scheduler.task_subscriber', TaskSubscriber::class)
        ->args([
            service('scheduler.registry'),
            service('scheduler.worker'),
        ])
        ->tag('kernel.event_subscriber')

        // Watcher
        ->set('scheduler.stop_watch', Stopwatch::class)
        ->set('scheduler.task_execution.watcher', TaskExecutionWatcher::class)
        ->args([
            service('scheduler.stop_watch'),
        ])
        ->alias(TaskExecutionWatcherInterface::class, 'scheduler.task_execution.watcher')

        // Worker
        ->set('scheduler.worker', SchedulerWorker::class)
        ->args([
            tagged_iterator('scheduler.runner'),
            service('scheduler.task_execution.watcher'),
            service('event_dispatcher')->nullOnInvalid(),
            service('logger')->nullOnInvalid()
        ])
        ->alias(WorkerInterface::class, 'scheduler.worker')
    ;
};
