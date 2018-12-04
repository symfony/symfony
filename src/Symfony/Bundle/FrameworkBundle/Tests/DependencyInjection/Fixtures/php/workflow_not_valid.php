<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest;

$container->loadFromExtension('framework', [
    'workflows' => [
        'my_workflow' => [
            'type' => 'state_machine',
            'supports' => [
                FrameworkExtensionTest::class,
            ],
            'places' => [
                'first',
                'middle',
                'last',
            ],
            'transitions' => [
                'go' => [
                    'from' => [
                        'first',
                    ],
                    'to' => [
                        'middle',
                        'last',
                    ],
                ],
            ],
        ],
    ],
]);
