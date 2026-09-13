<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes->import('php_dsl_sub_add_condition.php')
        ->addCondition('request.isSecure()');

    $routes->collection('c_')
        ->addCondition('request.isSecure()')
        ->add('foo', '/foo')
            ->addCondition('context.getMethod() == "GET"');
};
