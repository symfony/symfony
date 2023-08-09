<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'messenger' => [
        'enabled' => true,
    ],
    'mailer' => [
        'dsn' => 'smtp://example.com',
    ],
    'notifier' => [
        'message_bus' => 'app.another_bus',
        'chatter_transports' => [
            'test' => 'null'
        ],
        'texter_transports' => [
            'test' => 'null'
        ],
    ],
]);
