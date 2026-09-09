<?php

use Symfony\Component\Workflow\Tests\Fixtures\Places;
use Symfony\Component\Workflow\Tests\DependencyInjection\WorkflowBundleExtensionTest;

$container->loadFromExtension('workflow', [
    'enum' => [
        'supports' => [
            WorkflowBundleExtensionTest::class,
        ],
        'places' => Places::class.'::*',
        'transitions' => [
            'one' => [
                'from' => Places::A,
                'to' => Places::B,
            ],
            'two' => [
                'from' => Places::B,
                'to' => Places::C,
            ],
        ],
    ]
]);
