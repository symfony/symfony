<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes): void {
    $routes
        ->import(
            resource: [
                'path' => '../Psr4Controllers',
                'namespace' => 'Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers',
            ],
            type: 'attribute',
        )
        ->prefix('/my-prefix');
};
