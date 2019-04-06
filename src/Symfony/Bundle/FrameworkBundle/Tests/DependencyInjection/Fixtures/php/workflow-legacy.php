<?php

$container->loadFromExtension('framework', [
    'workflows' => [
        'legacy' => [
            'type' => 'state_machine',
            'marking_store' => [
                'type' => 'single_state',
                'arguments' => [
                    'state',
                ],
            ],
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
