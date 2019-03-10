<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes
        ->add('utf8_one', '/one')
        ->add('utf8_two', '/two')
    ;
};
