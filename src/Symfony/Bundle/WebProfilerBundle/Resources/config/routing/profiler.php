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
                trigger_deprecation('symfony/routing', '7.3', 'The "profiler.xml" routing configuration file is deprecated, import "profiler.php" instead.');

                break;
            }
        }
    }

    $routes->add('_profiler_home', '/')
        ->controller('web_profiler.controller.profiler::homeAction')
    ;
    $routes->add('_profiler_search', '/search')
        ->controller('web_profiler.controller.profiler::searchAction')
    ;
    $routes->add('_profiler_search_bar', '/search_bar')
        ->controller('web_profiler.controller.profiler::searchBarAction')
    ;
    $routes->add('_profiler_phpinfo', '/phpinfo')
        ->controller('web_profiler.controller.profiler::phpinfoAction')
    ;
    $routes->add('_profiler_xdebug', '/xdebug')
        ->controller('web_profiler.controller.profiler::xdebugAction')
    ;
    $routes->add('_profiler_font', '/font/{fontName}.woff2')
        ->controller('web_profiler.controller.profiler::fontAction')
    ;
    $routes->add('_profiler_search_results', '/{token}/search/results')
        ->controller('web_profiler.controller.profiler::searchResultsAction')
    ;
    $routes->add('_profiler_open_file', '/open')
        ->controller('web_profiler.controller.profiler::openAction')
    ;
    $routes->add('_profiler', '/{token}')
        ->controller('web_profiler.controller.profiler::panelAction')
    ;
    $routes->add('_profiler_router', '/{token}/router')
        ->controller('web_profiler.controller.router::panelAction')
    ;
    $routes->add('_profiler_exception', '/{token}/exception')
        ->controller('web_profiler.controller.exception_panel::body')
    ;
    $routes->add('_profiler_exception_css', '/{token}/exception.css')
        ->controller('web_profiler.controller.exception_panel::stylesheet')
    ;
};
