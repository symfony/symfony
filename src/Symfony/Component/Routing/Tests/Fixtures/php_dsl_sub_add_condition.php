<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes->add('with_condition', '/with-condition')
        ->condition('context.getMethod() == "GET"');

    $routes->add('without_condition', '/without-condition');
};
