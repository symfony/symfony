<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'messenger' => [
        'routing' => [
            DummyMessage::class => ['amqp'],
        ],
        'transports' => [
            'amqp' => 'amqp://localhost/%2f/messages',
        ],
    ],
]);
