<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'annotations' => ['enabled' => true],
    'serializer' => [
        'enable_annotations' => true,
        'mapping' => [
            'paths' => [
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/files',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yml',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yaml',
            ],
        ],
    ],
]);
