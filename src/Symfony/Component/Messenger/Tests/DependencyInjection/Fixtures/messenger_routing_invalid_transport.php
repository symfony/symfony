<?php

use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

$container->loadFromExtension('messenger', [
    'serializer' => [
        'default_serializer' => 'messenger.transport.symfony_serializer',
    ],
    'routing' => [
        DummyMessage::class => 'invalid',
    ],
    'transports' => [
        'amqp' => 'amqp://localhost/%2f/messages',
    ],
]);
