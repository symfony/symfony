<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'fragments' => [
        'enabled' => false,
    ],
    'esi' => [
        'enabled' => true,
    ],
    'ssi' => [
        'enabled' => true,
    ],
]);
