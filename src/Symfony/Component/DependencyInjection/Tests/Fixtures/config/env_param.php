<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container, string $env) {
    $container->extension('acme', [
        'color' => 'prod' === $env ? 'blue' : 'red',
    ]);
};
