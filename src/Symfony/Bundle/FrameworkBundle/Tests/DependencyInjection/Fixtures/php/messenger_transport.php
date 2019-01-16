<?php

$container->loadFromExtension('framework', [
    'serializer' => true,
    'messenger' => [
        'serializer' => [
            'id' => 'messenger.transport.symfony_serializer',
            'format' => 'csv',
            'context' => ['enable_max_depth' => true],
        ],
        'transports' => [
            'default' => 'amqp://localhost/%2f/messages',
        ],
    ],
]);
