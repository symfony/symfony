<?php

$container->loadFromExtension('messenger', [
    'serializer' => [
        'default_serializer' => 'messenger.transport.symfony_serializer',
        'symfony_serializer' => [
            'service' => 'serializer.api',
        ],
    ],
    'transports' => [
        'default' => 'amqp://localhost/%2f/messages',
    ],
]);
