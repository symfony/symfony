<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->set('foo', \stdClass::class)
        ->public()
        ->args([
            env('CCC')->int()
        ]);
};
