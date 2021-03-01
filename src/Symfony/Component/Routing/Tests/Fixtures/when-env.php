<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes
        ->when('some-env')
            ->add('a', '/a2')
            ->add('b', '/b');

    $routes
        ->when('some-other-env')
            ->add('a', '/a3')
            ->add('c', '/c');

    $routes
        ->add('a', '/a1');
};
