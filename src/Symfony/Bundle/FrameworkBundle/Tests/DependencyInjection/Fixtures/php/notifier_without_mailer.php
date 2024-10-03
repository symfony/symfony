<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'mailer' => [
        'enabled' => false,
    ],
    'messenger' => [
        'enabled' => true,
    ],
    'notifier' => [
        'enabled' => true,
        'notification_on_failed_messages' => true,
        'chatter_transports' => [
            'slack' => 'null',
        ],
        'texter_transports' => [
            'twilio' => 'null',
        ],
        'channel_policy' => [
            'low' => ['slack'],
            'high' => ['slack', 'twilio'],
        ],
        'admin_recipients' => [
            ['email' => 'test@test.de', 'phone' => '+490815',],
        ],
    ],
]);
