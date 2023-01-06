<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
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
