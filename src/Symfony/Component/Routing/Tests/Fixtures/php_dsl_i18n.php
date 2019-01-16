<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes
        ->collection()
        ->prefix(['en' => '/glish'])
        ->add('foo', '/foo')
        ->add('bar', ['en' => '/bar']);

    $routes
        ->add('baz', ['en' => '/baz']);

    $routes->import('php_dsl_sub_i18n.php')
        ->prefix(['fr' => '/ench']);
};
