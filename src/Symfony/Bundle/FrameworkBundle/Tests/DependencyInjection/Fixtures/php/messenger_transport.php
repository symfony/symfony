<?php

$container->loadFromExtension('framework', [
    'serializer' => true,
    'messenger' => [
        'serializer' => [
            'default_serializer' => 'messenger.transport.symfony_serializer',
            'symfony_serializer' => [
                'format' => 'csv',
                'context' => [
                    'enable_max_depth' => true,
                    'type_resolver' => 'App\Serializer\TypeResolver',
                ],
            ],
        ],
        'transports' => [
            'default' => 'amqp://localhost/%2f/messages',
        ],
    ],
]);
