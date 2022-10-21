<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('route', '/hello');
    $routes->add('overrided', '/');
    $routes->alias('alias', 'route');
    $routes->alias('deprecated', 'route')
        ->deprecate('foo/bar', '1.0.0', '');
    $routes->alias('deprecated-with-custom-message', 'route')
        ->deprecate('foo/bar', '1.0.0', 'foo %alias_id%.');
    $routes->alias('deep', 'alias');
    $routes->alias('overrided', 'route');
};
