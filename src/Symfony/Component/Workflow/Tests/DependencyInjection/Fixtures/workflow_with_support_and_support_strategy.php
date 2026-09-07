<?php

use Symfony\Component\Workflow\Tests\DependencyInjection\WorkflowBundleExtensionTest;

$container->loadFromExtension('workflow', [
    'my_workflow' => [
        'type' => 'workflow',
        'supports' => [
            WorkflowBundleExtensionTest::class,
        ],
        'support_strategy' => 'foobar',
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
