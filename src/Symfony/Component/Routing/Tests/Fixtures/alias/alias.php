<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('route', '/hello');
    $routes->add('overrided', '/');
    $routes->alias('alias', 'route');
    $routes->alias('alias2', 'route');
    $routes->alias('deep', 'alias');
    $routes->alias('overrided', 'route');
};
