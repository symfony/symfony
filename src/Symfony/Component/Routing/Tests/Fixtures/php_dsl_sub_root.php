<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $add = $routes->collection('r_');

    $add('root', '/');
    $add('bar', '/bar/');
};
