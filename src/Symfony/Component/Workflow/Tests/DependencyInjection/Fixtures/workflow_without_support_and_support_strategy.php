<?php

$container->loadFromExtension('workflow', [
    'my_workflow' => [
        'type' => 'workflow',
        'places' => [
            'first',
            'last',
        ],
        'transitions' => [
            'go' => [
                'from' => [
                    'first',
                ],
                'to' => [
                    'last',
                ],
            ],
        ],
    ],
]);
