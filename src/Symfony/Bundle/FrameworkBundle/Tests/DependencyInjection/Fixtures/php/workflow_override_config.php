<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->extension('framework', [
        'workflows' => [
            'test_workflow' => [
                'type' => 'workflow',
                'supports' => [
                    'Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase',
                ],
                'initial_marking' => ['start'],
                'places' => [
                    'start',
                    'middle',
                    'end',
                    'alternative',
                ],
                'transitions' => [
                    'base_transition' => [
                        'from' => ['middle'],
                        'to' => ['alternative'],
                    ],
                ],
            ],
        ],
    ]);
};
