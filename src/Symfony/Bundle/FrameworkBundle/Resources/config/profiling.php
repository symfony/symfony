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

use Symfony\Bundle\FrameworkBundle\EventListener\ConsoleProfilerListener;
use Symfony\Component\HttpKernel\Debug\VirtualRequestStack;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profiler;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('profiler', Profiler::class)
            ->public()
            ->args([service('profiler.storage'), service('logger')->nullOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'profiler'])
            ->tag('container.private', ['package' => 'symfony/framework-bundle', 'version' => '5.4'])

        ->set('profiler.storage', FileProfilerStorage::class)
            ->args([param('profiler.storage.dsn')])

        ->set('profiler_listener', ProfilerListener::class)
            ->args([
                service('profiler'),
                service('request_stack'),
                null,
                param('profiler_listener.only_exceptions'),
                param('profiler_listener.only_main_requests'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('console_profiler_listener', ConsoleProfilerListener::class)
            ->args([
                service('.lazy_profiler'),
                service('.virtual_request_stack'),
                service('debug.stopwatch'),
                param('kernel.runtime_mode.cli'),
                service('router')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')

        ->set('.lazy_profiler', Profiler::class)
            ->factory('current')
            ->args([[service('profiler')]])
            ->lazy()

        ->set('.virtual_request_stack', VirtualRequestStack::class)
            ->args([service('request_stack')])
            ->public()
    ;
};
