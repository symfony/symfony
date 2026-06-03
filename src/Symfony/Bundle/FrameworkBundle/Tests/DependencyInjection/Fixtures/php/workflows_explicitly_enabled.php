<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase;

$container->loadFromExtension('framework', [
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
