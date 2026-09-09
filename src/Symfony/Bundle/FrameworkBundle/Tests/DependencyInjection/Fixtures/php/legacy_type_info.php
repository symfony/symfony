<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()->alias('test_type_info_context_factory', 'type_info.type_context_factory')->public();
    $container->extension('framework', [
        'type_info' => [
            'aliases' => [
                'CustomAlias' => 'int',
            ],
        ],
    ]);
};
