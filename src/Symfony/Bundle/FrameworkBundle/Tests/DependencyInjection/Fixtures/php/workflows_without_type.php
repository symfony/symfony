<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest;

$container->loadFromExtension('framework', [
    'workflows' => [
        'missing_type' => [
            'marking_store' => [
                'service' => 'workflow_service',
            ],
            'supports' => [
                \stdClass::class,
            ],
            'places' => [
                'first',
                'last',
            ],
            'transitions' => [
                'go' => [
                    'from' => 'first',
                    'to' => 'last',
                ],
            ],
        ],
    ],
]);
