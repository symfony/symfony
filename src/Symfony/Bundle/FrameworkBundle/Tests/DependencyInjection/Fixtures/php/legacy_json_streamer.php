<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()->alias('test_json_streamer_stream_writer', 'json_streamer.stream_writer')->public();
    $container->extension('framework', [
        'json_streamer' => [
            'default_options' => [
                'include_null_properties' => true,
            ],
        ],
    ]);
};
