<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    if ('some-env' === $routes->env()) {
        $routes->add('b', '/b');
        $routes->add('a', '/a2');
    } elseif ('some-other-env' === $routes->env()) {
        $routes->add('a', '/a3');
        $routes->add('c', '/c');
    }

    $routes->add('a', '/a1');
};
