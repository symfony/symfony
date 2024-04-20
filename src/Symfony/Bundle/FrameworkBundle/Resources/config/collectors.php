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

use Symfony\Bundle\FrameworkBundle\DataCollector\RouterDataCollector;
use Symfony\Component\Console\DataCollector\CommandDataCollector;
use Symfony\Component\HttpKernel\DataCollector\AjaxDataCollector;
use Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector;
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\KernelEvents;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('data_collector.config', ConfigDataCollector::class)
            ->call('setKernel', [service('kernel')->ignoreOnInvalid()])
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/config.html.twig', 'id' => 'config', 'priority' => -255])

        ->set('data_collector.request', RequestDataCollector::class)
            ->args([
                service('.virtual_request_stack')->ignoreOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/request.html.twig', 'id' => 'request', 'priority' => 335])

        ->set('data_collector.request.session_collector', \Closure::class)
            ->factory([\Closure::class, 'fromCallable'])
            ->args([[service('data_collector.request'), 'collectSessionUsage']])

        ->set('data_collector.ajax', AjaxDataCollector::class)
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/ajax.html.twig', 'id' => 'ajax', 'priority' => 315])

        ->set('data_collector.exception', ExceptionDataCollector::class)
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/exception.html.twig', 'id' => 'exception', 'priority' => 305])

        ->set('data_collector.events', EventDataCollector::class)
            ->args([
                tagged_iterator('event_dispatcher.dispatcher', 'name'),
                service('.virtual_request_stack')->ignoreOnInvalid(),
            ])
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/events.html.twig', 'id' => 'events', 'priority' => 290])

        ->set('data_collector.logger', LoggerDataCollector::class)
            ->args([
                service('logger')->ignoreOnInvalid(),
                sprintf('%s/%s', param('kernel.build_dir'), param('kernel.container_class')),
                service('.virtual_request_stack')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'profiler'])
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/logger.html.twig', 'id' => 'logger', 'priority' => 300])

        ->set('data_collector.time', TimeDataCollector::class)
            ->args([
                service('kernel')->ignoreOnInvalid(),
                service('debug.stopwatch')->ignoreOnInvalid(),
            ])
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/time.html.twig', 'id' => 'time', 'priority' => 330])

        ->set('data_collector.memory', MemoryDataCollector::class)
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/memory.html.twig', 'id' => 'memory', 'priority' => 325])

        ->set('data_collector.router', RouterDataCollector::class)
            ->tag('kernel.event_listener', ['event' => KernelEvents::CONTROLLER, 'method' => 'onKernelController'])
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/router.html.twig', 'id' => 'router', 'priority' => 285])

        ->set('.data_collector.command', CommandDataCollector::class)
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/command.html.twig', 'id' => 'command', 'priority' => 335])
    ;
};
