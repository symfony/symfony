<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'workflows' => [
        'enabled' => true,
        'foo' => [
            'type' => 'workflow',
            'supports' => ['Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest'],
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
