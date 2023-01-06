<?php

namespace Symfony\Component\Routing\Loader\Configurator;

use Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\MyController;

return function (RoutingConfigurator $routes): void {
    $routes
        ->import(
            resource: MyController::class,
            type: 'attribute',
        )
        ->prefix('/my-prefix');
};
