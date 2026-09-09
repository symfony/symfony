<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()->alias('test_workflow', 'workflow.test_workflow')->public();
    $container->extension('workflow', [
        'test_workflow' => [
            'type' => 'workflow',
            'supports' => [
                'Symfony\Component\Workflow\Tests\DependencyInjection\WorkflowBundleExtensionTest',
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
    ]);
};
