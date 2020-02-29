<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes->import('imported-with-defaults.php')
        ->prefix('/defaults')
        ->locale('g_locale')
        ->format('g_format')
        ->stateless(true)
    ;
};
