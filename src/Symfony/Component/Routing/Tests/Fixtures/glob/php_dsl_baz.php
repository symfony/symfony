<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $collection = $routes->collection();

    $collection->add('baz_route', '/baz')
        ->defaults(['_controller' => 'AppBundle:Baz:view']);

    return $collection;
};
