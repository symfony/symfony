<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes
        ->add('one', '/one')
        ->add('two', '/two')->defaults(['specific' => 'imported'])
    ;
};
