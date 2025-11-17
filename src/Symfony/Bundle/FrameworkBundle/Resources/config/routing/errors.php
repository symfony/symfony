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
    $routes->add('_preview_error', '/{code}.{_format}')
        ->controller('error_controller::preview')
        ->defaults(['_format' => 'html'])
        ->requirements(['code' => '\d+'])
    ;
};
