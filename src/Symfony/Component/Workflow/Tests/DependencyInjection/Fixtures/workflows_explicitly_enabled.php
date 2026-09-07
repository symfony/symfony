<?php

use Symfony\Component\Workflow\Tests\DependencyInjection\WorkflowBundleExtensionTest;

$container->loadFromExtension('workflow', [
    'enabled' => true,
    'foo' => [
        'type' => 'workflow',
        'supports' => [WorkflowBundleExtensionTest::class],
        'initial_marking' => ['bar'],
        'places' => ['bar', 'baz'],
        'transitions' => [
            'bar_baz' => [
                'from' => ['bar'],
                'to' => ['baz'],
            ],
        ],
    ],
]);
