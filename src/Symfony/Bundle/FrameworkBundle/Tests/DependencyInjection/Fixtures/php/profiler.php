<?php

$container->loadFromExtension('framework', [
    'profiler' => [
        'enabled' => true,
        'collect_serializer_data' => true,
    ],
    'serializer' => [
        'enabled' => true,
    ],
]);
