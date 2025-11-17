<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('_wdt_stylesheet', '/styles')
        ->controller('web_profiler.controller.profiler::toolbarStylesheetAction')
    ;
    $routes->add('_wdt', '/{token}')
        ->controller('web_profiler.controller.profiler::toolbarAction')
    ;
};
