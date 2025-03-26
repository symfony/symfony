<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'type_info' => [
        'enabled' => true,
    ],
    'json_streamer' => [
        'enabled' => true,
    ],
]);
