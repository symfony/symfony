<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes->import('imported.php')->host([
        'nl' => 'www.example.nl',
        'en' => 'www.example.com',
    ])->prefix([
        'nl' => '/nl',
        'en' => '/en',
    ]);
};
