<?php

$container->loadFromExtension('framework', [
    'profiler' => [
        'enabled' => true,
        'excluded_paths' => ['^/\.well-known/'],
        'excluded_http_codes' => [
            404 => null,
            400 => ['^/foo', '^/bar'],
        ],
    ],
]);
