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
use Symfony\Component\Routing\Loader\XmlFileLoader;

return function (RoutingConfigurator $routes): void {
    foreach (debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT) as $trace) {
        if (isset($trace['object']) && $trace['object'] instanceof XmlFileLoader && 'doImport' === $trace['function']) {
            if (__DIR__ === dirname(realpath($trace['args'][3]))) {
                trigger_deprecation('symfony/routing', '7.3', 'The "wdt.xml" routing configuration file is deprecated, import "wdt.php" instead.');

                break;
            }
        }
    }

    $routes->add('_wdt_stylesheet', '/styles')
        ->controller('web_profiler.controller.profiler::toolbarStylesheetAction')
    ;
    $routes->add('_wdt', '/{token}')
        ->controller('web_profiler.controller.profiler::toolbarAction')
    ;
};
