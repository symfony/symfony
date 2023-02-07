<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'workflows' => [
        'my_workflow' => [
            'type' => 'workflow',
            'supports' => [
                FrameworkExtensionTestCase::class,
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
    ],
]);
