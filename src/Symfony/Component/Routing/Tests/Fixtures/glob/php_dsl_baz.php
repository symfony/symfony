<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $collection = $routes->collection();

    $collection->add('baz_route', '/baz')
        ->defaults(array('_controller' => 'AppBundle:Baz:view'));

    return $collection;
};
