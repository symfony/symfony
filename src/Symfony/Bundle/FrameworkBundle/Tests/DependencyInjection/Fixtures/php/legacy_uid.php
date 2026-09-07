<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()
        ->alias('test_uuid_factory', 'uuid.factory')->public()
        ->alias('test_uuid47_transformer', 'uuid47_transformer')->public()
    ;
    $container->extension('framework', [
        'uid' => [
            'default_uuid_version' => 6,
            'name_based_uuid_namespace' => '73902feb-9b95-4fe5-9c6f-b3e6d29e77b5',
        ],
    ]);
};
