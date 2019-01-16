<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes
        ->collection()
        ->add('foo', '/foo')
            ->condition('abc')
            ->options(['utf8' => true])
        ->add('buz', 'zub')
            ->controller('foo:act');

    $routes->import('php_dsl_sub.php')
        ->prefix('/sub')
        ->requirements(['id' => '\d+']);

    $routes->import('php_dsl_sub.php')
        ->namePrefix('z_')
        ->prefix('/zub');

    $routes->import('php_dsl_sub_root.php')
        ->prefix('/bus', false);

    $routes->add('ouf', '/ouf')
        ->schemes(['https'])
        ->methods(['GET'])
        ->defaults(['id' => 0]);
};
