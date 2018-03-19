<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $add = $routes->collection('c_')
        ->prefix('pub');

    $add('foo', array('fr' => '/foo'));
    $add('bar', array('fr' => '/bar'));
};
