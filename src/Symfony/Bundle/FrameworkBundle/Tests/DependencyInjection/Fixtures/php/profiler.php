<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'profiler' => [
        'enabled' => true,
    ],
    'serializer' => [
        'enabled' => true
    ],
]);
