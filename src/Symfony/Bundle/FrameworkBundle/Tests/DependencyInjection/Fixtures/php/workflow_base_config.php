<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()->alias('test_workflow', 'workflow.test_workflow')->public();
    $container->extension('framework', [
        'http_method_override' => false,
        'handle_all_throwables' => true,
        'annotations' => [
            'enabled' => false,
        ],
        'php_errors' => [
            'log' => true,
        ],
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
                        'from' => ['start'],
                        'to' => ['end'],
                    ],
                ],
            ],
        ],
    ]);
};
