<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'workflows' => [
        'my_workflow' => [
            'type' => 'state_machine',
            'marking_store' => [
                'type' => 'method',
                'property' => 'state',
            ],
            'supports' => [
                FrameworkExtensionTestCase::class,
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
                        'three',
                    ],
                ],
            ],
        ],
    ],
]);
