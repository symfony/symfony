<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'serializer' => true,
    'messenger' => [
        'transports' => [
            'invalid_transport' => [
                'dsn' => 'null://',
                'incoming_message_serializer' => [
                    'messageClass' => BarMessage::class,
                    'messageClassResolver' => 'foo',
                ],
            ]
        ],
    ],
]);
