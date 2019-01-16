<?php

$container->loadFromExtension('framework', [
    'serializer' => true,
    'messenger' => [
        'serializer' => 'messenger.transport.symfony_serializer',
        'routing' => [
            'Symfony\Component\Messenger\Tests\Fixtures\DummyMessage' => ['amqp', 'audit'],
            'Symfony\Component\Messenger\Tests\Fixtures\SecondMessage' => [
                'senders' => ['amqp', 'audit'],
                'send_and_handle' => true,
            ],
            '*' => 'amqp',
        ],
        'transports' => [
            'amqp' => 'amqp://localhost/%2f/messages',
        ],
    ],
]);
