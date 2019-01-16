<?php

$container->loadFromExtension('framework', [
    'messenger' => [
        'serializer' => [
            'enabled' => false,
        ],
        'transports' => [
            'default' => 'amqp://localhost/%2f/messages',
        ],
    ],
]);
