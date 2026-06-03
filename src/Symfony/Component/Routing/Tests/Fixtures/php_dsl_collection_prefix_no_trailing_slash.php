<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $sub = $routes->collection('c_')->prefix('/categories', false);

    $sub->add('slash', '/');
    $sub->add('empty', '');
    $sub->add('show', '/{id}');
};
