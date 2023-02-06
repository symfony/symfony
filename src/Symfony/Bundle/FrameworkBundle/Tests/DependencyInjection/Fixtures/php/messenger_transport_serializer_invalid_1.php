<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'serializer' => true,
    'messenger' => [
        'transports' => [
            'invalid_transport' => [
                'dsn' => 'null://',
                'serializer' => 'messenger.transport.native_php_serializer',
                'outgoing_message_serializer' => []
            ]
        ],
    ],
]);
