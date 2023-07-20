<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'message_bus' => 'app.another_bus',
    ],
]);
