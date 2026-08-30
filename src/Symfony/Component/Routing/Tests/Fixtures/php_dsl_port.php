<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $api = $routes->collection('api_')->port(8001);
    $api->add('users', '/users');
    $api->add('posts', '/posts');

    $routes->import('php_dsl_sub.php')
        ->prefix('/admin')
        ->port(8002);

    $routes->add('front', '/front');
};
