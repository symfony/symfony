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

use Symfony\Bundle\WebProfilerBundle\EventListener\TurboDriveCspListener;
use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder) {
    $services = $container->services();

    $services->set('web_profiler.debug_toolbar', WebDebugToolbarListener::class)
        ->args([
            service('twig'),
            param('web_profiler.debug_toolbar.intercept_redirects'),
            param('web_profiler.debug_toolbar.mode'),
            service('router')->ignoreOnInvalid(),
            abstract_arg('paths that should be excluded from the AJAX requests shown in the toolbar'),
            service('web_profiler.csp.handler'),
            service('data_collector.dump')->ignoreOnInvalid(),
        ])
        ->tag('kernel.event_subscriber');

    $bundles = $containerBuilder->getParameter('kernel.bundles');
    if (\is_array($bundles) && isset($bundles['TurboBundle'])) {
        $services->set('web_profiler.turbo_drive_csp', TurboDriveCspListener::class)
            ->args([
                service('kernel'),
            ])
            ->tag('kernel.event_subscriber');
    }
};
