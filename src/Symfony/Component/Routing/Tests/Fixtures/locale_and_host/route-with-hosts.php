<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes->add('static', '/example')->host([
        'nl' => 'www.example.nl',
        'en' => 'www.example.com',
    ]);
};
