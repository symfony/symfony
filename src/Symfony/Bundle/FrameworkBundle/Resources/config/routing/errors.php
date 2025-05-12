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
    foreach (debug_backtrace() as $trace) {
        if (isset($trace['object']) && $trace['object'] instanceof XmlFileLoader && 'doImport' === $trace['function']) {
            if (__DIR__ === dirname(realpath($trace['args'][3]))) {
                trigger_deprecation('symfony/routing', '7.3', 'The "errors.xml" routing configuration file is deprecated, import "errors.php" instead.');

                break;
            }
        }
    }

    $routes->add('_preview_error', '/{code}.{_format}')
        ->controller('error_controller::preview')
        ->defaults(['_format' => 'html'])
        ->requirements(['code' => '\d+'])
    ;
};
