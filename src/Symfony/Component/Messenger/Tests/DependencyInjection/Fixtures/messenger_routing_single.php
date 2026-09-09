<?php

use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

$container->loadFromExtension('messenger', [
    'routing' => [
        DummyMessage::class => ['amqp'],
    ],
    'transports' => [
        'amqp' => 'amqp://localhost/%2f/messages',
    ],
]);
