<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()
        ->alias('test_cache_app', 'cache.app')->public()
    ;
    $container->extension('framework', [
        'cache' => [
            'prefix_seed' => 'my-app',
            'app' => 'cache.adapter.array',
        ],
    ]);
};
