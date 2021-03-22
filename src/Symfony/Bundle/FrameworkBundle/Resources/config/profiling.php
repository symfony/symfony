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

use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profiler;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('profiler', Profiler::class)
            ->public()
            ->args([service('profiler.storage'), service('logger')->nullOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'profiler'])

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
    ;
};
