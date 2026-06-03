<?php

$container->loadFromExtension('framework', [
    'cache' => [
        'app' => 'cache.adapter.redis_tag_aware',
    ],
]);
