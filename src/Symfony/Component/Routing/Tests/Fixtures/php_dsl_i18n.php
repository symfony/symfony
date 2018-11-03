<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes
        ->collection()
        ->prefix(array('en' => '/glish'))
        ->add('foo', '/foo')
        ->add('bar', array('en' => '/bar'));

    $routes
        ->add('baz', array('en' => '/baz'));

    $routes->import('php_dsl_sub_i18n.php')
        ->prefix(array('fr' => '/ench'));
};
