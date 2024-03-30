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

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\NotTaggedControllerValueResolver;
use Symfony\Component\HttpKernel\Controller\TraceableArgumentResolver;
use Symfony\Component\HttpKernel\Controller\TraceableControllerResolver;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('debug.event_dispatcher', TraceableEventDispatcher::class)
            ->decorate('event_dispatcher')
            ->args([
                service('debug.event_dispatcher.inner'),
                service('debug.stopwatch'),
                service('logger')->nullOnInvalid(),
                service('.virtual_request_stack')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'event'])
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('debug.controller_resolver', TraceableControllerResolver::class)
            ->decorate('controller_resolver')
            ->args([
                service('debug.controller_resolver.inner'),
                service('debug.stopwatch'),
            ])

        ->set('debug.argument_resolver', TraceableArgumentResolver::class)
            ->decorate('argument_resolver')
            ->args([
                service('debug.argument_resolver.inner'),
                service('debug.stopwatch'),
            ])

        ->set('argument_resolver.not_tagged_controller', NotTaggedControllerValueResolver::class)
            ->args([abstract_arg('Controller argument, set in FrameworkExtension')])
            ->tag('controller.argument_value_resolver', ['priority' => -200])
    ;
};
