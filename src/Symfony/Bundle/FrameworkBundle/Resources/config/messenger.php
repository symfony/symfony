<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnCustomStopExceptionListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnSigtermSignalListener;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\RejectRedeliveredMessageMiddleware;
use Symfony\Component\Messenger\Middleware\RouterContextMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\TraceableMiddleware;
use Symfony\Component\Messenger\Middleware\ValidationMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\FlattenExceptionNormalizer;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->alias(SerializerInterface::class, 'messenger.default_serializer')

        // Asynchronous
        ->set('messenger.senders_locator', SendersLocator::class)
            ->args([
                abstract_arg('per message senders map'),
                abstract_arg('senders service locator'),
            ])
        ->set('messenger.middleware.send_message', SendMessageMiddleware::class)
            ->args([
                service('messenger.senders_locator'),
                service('event_dispatcher'),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'messenger'])

        // Message encoding/decoding
        ->set('messenger.transport.symfony_serializer', Serializer::class)
            ->args([
                service('serializer'),
                abstract_arg('format'),
                abstract_arg('context'),
            ])

        ->set('serializer.normalizer.flatten_exception', FlattenExceptionNormalizer::class)
            ->tag('serializer.normalizer', ['priority' => -880])

        ->set('messenger.transport.native_php_serializer', PhpSerializer::class)

        // Middleware
        ->set('messenger.middleware.handle_message', HandleMessageMiddleware::class)
            ->abstract()
            ->args([
                abstract_arg('bus handler resolver'),
            ])
            ->tag('monolog.logger', ['channel' => 'messenger'])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])

        ->set('messenger.middleware.add_bus_name_stamp_middleware', AddBusNameStampMiddleware::class)
            ->abstract()

        ->set('messenger.middleware.dispatch_after_current_bus', DispatchAfterCurrentBusMiddleware::class)

        ->set('messenger.middleware.validation', ValidationMiddleware::class)
            ->args([
                service('validator'),
            ])

        ->set('messenger.middleware.reject_redelivered_message_middleware', RejectRedeliveredMessageMiddleware::class)

        ->set('messenger.middleware.failed_message_processing_middleware', FailedMessageProcessingMiddleware::class)

        ->set('messenger.middleware.traceable', TraceableMiddleware::class)
            ->abstract()
            ->args([
                service('debug.stopwatch'),
            ])

        ->set('messenger.middleware.router_context', RouterContextMiddleware::class)
            ->args([
                service('router'),
            ])

        // Discovery
        ->set('messenger.receiver_locator', ServiceLocator::class)
            ->args([
                [],
            ])
            ->tag('container.service_locator')

        // Transports
        ->set('messenger.transport_factory', TransportFactory::class)
            ->args([
                tagged_iterator('messenger.transport_factory'),
            ])

        ->set('messenger.transport.amqp.factory', AmqpTransportFactory::class)

        ->set('messenger.transport.redis.factory', RedisTransportFactory::class)

        ->set('messenger.transport.sync.factory', SyncTransportFactory::class)
            ->args([
                service('messenger.routable_message_bus'),
            ])
            ->tag('messenger.transport_factory')

        ->set('messenger.transport.in_memory.factory', InMemoryTransportFactory::class)
            ->tag('messenger.transport_factory')
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('messenger.transport.sqs.factory', AmazonSqsTransportFactory::class)
            ->args([
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('messenger.transport.beanstalkd.factory', BeanstalkdTransportFactory::class)

        // retry
        ->set('messenger.retry_strategy_locator', ServiceLocator::class)
            ->args([
                [],
            ])
            ->tag('container.service_locator')

        ->set('messenger.retry.abstract_multiplier_retry_strategy', MultiplierRetryStrategy::class)
            ->abstract()
            ->args([
                abstract_arg('max retries'),
                abstract_arg('delay ms'),
                abstract_arg('multiplier'),
                abstract_arg('max delay ms'),
            ])

        // worker event listener
        ->set('messenger.retry.send_failed_message_for_retry_listener', SendFailedMessageForRetryListener::class)
            ->args([
                abstract_arg('senders service locator'),
                service('messenger.retry_strategy_locator'),
                service('logger')->ignoreOnInvalid(),
                service('event_dispatcher'),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('messenger.failure.add_error_details_stamp_listener', AddErrorDetailsStampListener::class)
            ->tag('kernel.event_subscriber')

        ->set('messenger.failure.send_failed_message_to_failure_transport_listener', SendFailedMessageToFailureTransportListener::class)
            ->args([
                abstract_arg('failure transports'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('messenger.listener.dispatch_pcntl_signal_listener', DispatchPcntlSignalListener::class)
            ->tag('kernel.event_subscriber')

        ->set('messenger.listener.stop_worker_on_restart_signal_listener', StopWorkerOnRestartSignalListener::class)
            ->args([
                service('cache.messenger.restart_workers_signal'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('messenger.listener.stop_worker_on_sigterm_signal_listener', StopWorkerOnSigtermSignalListener::class)
            ->args([
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('messenger.listener.stop_worker_on_stop_exception_listener', StopWorkerOnCustomStopExceptionListener::class)
            ->tag('kernel.event_subscriber')

        ->set('messenger.listener.reset_services', ResetServicesListener::class)
            ->args([
                service('services_resetter'),
            ])

        ->set('messenger.routable_message_bus', RoutableMessageBus::class)
            ->args([
                abstract_arg('message bus locator'),
                service('messenger.default_bus'),
            ])
    ;
};
