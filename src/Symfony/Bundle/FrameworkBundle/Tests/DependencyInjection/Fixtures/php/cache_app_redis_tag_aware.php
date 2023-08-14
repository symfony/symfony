<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'cache' => [
        'app' => 'cache.adapter.redis_tag_aware',
    ],
]);
