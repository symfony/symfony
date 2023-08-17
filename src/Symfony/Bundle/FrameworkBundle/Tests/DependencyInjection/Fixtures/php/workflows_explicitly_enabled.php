<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase;

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'workflows' => [
        'enabled' => true,
        'foo' => [
            'type' => 'workflow',
            'supports' => [FrameworkExtensionTestCase::class],
            'initial_marking' => ['bar'],
            'places' => ['bar', 'baz'],
            'transitions' => [
                'bar_baz' => [
                    'from' => ['bar'],
                    'to' => ['baz'],
                ],
            ],
        ],
    ],
]);
