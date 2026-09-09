<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()->alias('test_html_sanitizer', 'html_sanitizer.sanitizer.custom')->public();
    $container->extension('framework', [
        'html_sanitizer' => [
            'sanitizers' => [
                'custom' => [
                    'default_action' => 'block',
                ],
            ],
        ],
    ]);
};
