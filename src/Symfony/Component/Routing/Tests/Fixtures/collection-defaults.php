<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $collection = $routes->collection();
    $collection
        ->methods(['GET'])
        ->defaults(['attribute' => true])
        ->stateless();

    $collection->add('defaultsA', '/defaultsA')
        ->locale('en')
        ->format('html');

    $collection->add('defaultsB', '/defaultsB')
        ->methods(['POST'])
        ->stateless(false)
        ->locale('en')
        ->format('html');
};
