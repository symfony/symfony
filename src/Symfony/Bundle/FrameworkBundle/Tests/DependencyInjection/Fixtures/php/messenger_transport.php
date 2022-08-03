<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'serializer' => true,
    'messenger' => [
        'serializer' => [
            'default_serializer' => 'messenger.transport.symfony_serializer',
            'symfony_serializer' => [
                'format' => 'csv',
                'context' => ['enable_max_depth' => true],
            ],
        ],
        'transports' => [
            'default' => 'amqp://localhost/%2f/messages',
        ],
    ],
]);
