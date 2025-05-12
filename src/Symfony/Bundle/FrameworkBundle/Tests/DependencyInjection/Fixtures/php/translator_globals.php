<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'translator' => [
        'globals' => [
            '%%app_name%%' => 'My application',
            '{app_version}' => '1.2.3',
            '{url}' => ['message' => 'url', 'parameters' => ['scheme' => 'https://'], 'domain' => 'global'],
        ],
    ],
]);
