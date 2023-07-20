<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'profiler' => [
        'enabled' => true,
        'collect_serializer_data' => true,
    ],
    'serializer' => [
        'enabled' => true,
    ]
]);
