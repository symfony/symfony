<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $add = $routes->collection('c_')
        ->prefix('pub');

    $add('foo', ['fr' => '/foo']);
    $add('bar', ['fr' => '/bar']);

    $routes->add('non_localized', '/non-localized');
};
