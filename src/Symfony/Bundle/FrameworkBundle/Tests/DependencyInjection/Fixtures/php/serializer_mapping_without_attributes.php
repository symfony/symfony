<?php

$container->loadFromExtension('framework', [
    'serializer' => [
        'enable_attributes' => true,
        'mapping' => [
            'paths' => [
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/files',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yml',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yaml',
            ],
        ],
    ],
]);
