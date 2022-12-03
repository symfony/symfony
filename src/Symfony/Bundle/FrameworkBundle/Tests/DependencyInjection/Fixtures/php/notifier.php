<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'messenger' => [
        'enabled' => true
    ],
    'mailer' => [
        'dsn' => 'smtp://example.com',
    ],
    'notifier' => [
        'enabled' => true,
        'notification_on_failed_messages' => true,
        'chatter_transports' => [
            'slack' => 'null'
        ],
        'texter_transports' => [
            'twilio' => 'null'
        ],
        'channel_policy' => [
            'low' => ['slack'],
            'high' => ['slack', 'twilio'],
        ],
        'admin_recipients' => [
            ['email' => 'test@test.de', 'phone' => '+490815',],
        ]
    ],
]);
