<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\Workflow\Places;
use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase;

$container->loadFromExtension('framework', [
    'workflows' => [
        'enum' => [
            'supports' => [
                FrameworkExtensionTestCase::class,
            ],
            'places' => Places::cases(),
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
    ],
]);
