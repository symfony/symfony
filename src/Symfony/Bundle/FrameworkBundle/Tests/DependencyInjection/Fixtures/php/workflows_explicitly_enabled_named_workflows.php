<?php

$container->loadFromExtension('framework', [
    'workflows' => [
        'enabled' => true,
        'workflows' => [
            'type' => 'workflow',
            'supports' => ['Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest'],
            'initial_place' => 'bar',
            'places' => ['bar', 'baz'],
            'transitions' => [
                'bar_baz' => [
                    'from' => ['foo'],
                    'to' => ['bar'],
                ],
            ],
        ],
    ],
]);
