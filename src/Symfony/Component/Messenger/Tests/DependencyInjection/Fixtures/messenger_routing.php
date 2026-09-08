<?php

use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;

$container->loadFromExtension('messenger', [
    'serializer' => [
        'default_serializer' => 'messenger.transport.symfony_serializer',
    ],
    'routing' => [
        DummyMessage::class => ['amqp', 'messenger.transport.audit'],
        SecondMessage::class => ['amqp', 'audit'],
        'Symfony\*' => 'amqp',
        '*' => 'amqp',
    ],
    'transports' => [
        'amqp' => 'amqp://localhost/%2f/messages',
        'audit' => 'null://',
    ],
]);
