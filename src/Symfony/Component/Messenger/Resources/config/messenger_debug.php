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

use Symfony\Component\Console\Debug\CliRequest;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\Messenger\DataCollector\WorkerMessageDataCollector;
use Symfony\Component\Messenger\EventListener\WorkerProfilerListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('data_collector.messenger', MessengerDataCollector::class)
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/messenger.html.twig',
                'id' => 'messenger',
                'priority' => 100,
            ])
            ->tag('container.remove_if_missing', ['service' => 'profiler'])

        ->set('.data_collector.worker_message', WorkerMessageDataCollector::class)
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/worker_message.html.twig',
                'id' => 'worker_message',
                'priority' => 335,
            ])
            ->tag('container.remove_if_missing', ['service' => 'profiler'])

        ->set('messenger.listener.worker_profiler', WorkerProfilerListener::class)
            ->args([
                service('.lazy_profiler'),
                service('.virtual_request_stack'),
                service('debug.stopwatch'),
                service('router')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('container.remove_if_missing', ['service' => '.lazy_profiler'])
            ->tag('container.remove_if_missing', ['service' => '.virtual_request_stack'])
            ->tag('container.remove_if_missing', ['service' => 'debug.stopwatch'])
            ->tag('container.remove_if_missing', ['class' => CliRequest::class])
    ;
};
