<?php

$container->loadFromExtension('framework', [
    'workflows' => [
        'enabled' => true,
        'foo' => [
            'type' => 'workflow',
            'supports' => ['Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase'],
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
