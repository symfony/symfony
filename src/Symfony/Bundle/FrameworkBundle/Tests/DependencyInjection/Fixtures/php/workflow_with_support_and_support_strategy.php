<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase;

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
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
