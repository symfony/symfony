<?php

$container->loadFromExtension('framework', [
    'workflows' => [
        'enabled' => true,
        'workflows' => [
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
