<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage;

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'messenger' => [
        'routing' => [
            DummyMessage::class => ['amqp'],
        ],
        'transports' => [
            'amqp' => 'amqp://localhost/%2f/messages',
        ],
    ],
]);
