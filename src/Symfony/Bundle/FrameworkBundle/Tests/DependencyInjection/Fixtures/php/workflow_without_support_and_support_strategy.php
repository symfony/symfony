<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'workflows' => [
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
    ],
]);
