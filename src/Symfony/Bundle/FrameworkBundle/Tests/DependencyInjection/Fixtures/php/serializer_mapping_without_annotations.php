<?php

$container->loadFromExtension('framework', [
    'serializer' => [
        'enable_annotations' => false,
        'mapping' => [
            'paths' => [
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/files',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yml',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yaml',
            ],
        ],
    ],
]);
