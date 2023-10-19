<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\SecondMessage;

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'serializer' => true,
    'messenger' => [
        'serializer' => [
            'default_serializer' => 'messenger.transport.symfony_serializer',
        ],
        'routing' => [
            DummyMessage::class => ['amqp', 'messenger.transport.audit'],
            SecondMessage::class => [
                'senders' => ['amqp', 'audit'],
            ],
            'Symfony\*' => 'amqp',
            '*' => 'amqp',
        ],
        'transports' => [
            'amqp' => 'amqp://localhost/%2f/messages',
            'audit' => 'null://',
        ],
    ],
]);
