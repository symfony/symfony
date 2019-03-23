<?php

$container->loadFromExtension('framework', [
    'workflows' => [
        'legacy' => [
            'type' => 'workflow',
            'supports' => [
                stdClass::class,
            ],
            'initial_place' => 'draft',
            'places' => [
                'draft',
                'published',
            ],
            'transitions' => [
                'publish' => [
                    'from' => 'draft',
                    'to' => 'published',
                ],
            ],
        ],
    ],
]);
