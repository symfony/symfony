<?php

use Symfony\Component\Workflow\Tests\DependencyInjection\WorkflowBundleExtensionTest;

$container->loadFromExtension('workflow', [
    'my_workflow' => [
        'type' => 'state_machine',
        'supports' => [
            WorkflowBundleExtensionTest::class,
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
]);
