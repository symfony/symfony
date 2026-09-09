<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()->alias('test_workflow', 'workflow.article')->public();
    $container->extension('framework', [
        'workflows' => [
            'article' => [
                'type' => 'workflow',
                'supports' => [
                    'Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase',
                ],
                'initial_marking' => ['draft'],
                'places' => [
                    'draft',
                    'published',
                ],
                'transitions' => [
                    'publish' => [
                        'from' => ['draft'],
                        'to' => ['published'],
                    ],
                ],
            ],
        ],
    ]);
};
