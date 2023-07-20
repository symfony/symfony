<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'profiler' => [
        'enabled' => true,
    ],
    'serializer' => [
        'enabled' => true
    ],
]);
