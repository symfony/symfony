<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\SecondMessage;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
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
