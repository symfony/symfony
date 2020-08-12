<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest;

$container->loadFromExtension('framework', [
    'workflows' => [
        'my_workflow' => [
            'type' => 'state_machine',
            'marking_store' => [
                'type' => 'method',
                'property' => 'state'
            ],
            'supports' => [
                FrameworkExtensionTest::class,
            ],
            'events_to_dispatch' => [
                'workflow.leave',
                'workflow.completed',
            ],
            'places' => [
                'one',
                'two',
                'three',
            ],
            'transitions' => [
                'count_to_two' => [
                    'from' => [
                        'one',
                    ],
                    'to' => [
                        'two',
                    ],
                ],
                'count_to_three' => [
                    'from' => [
                        'two',
                    ],
                    'to' => [
                        'three'
                    ]
                ]
            ],
        ],
    ],
]);
