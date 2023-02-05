<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'serializer' => true,
    'messenger' => [
        'serializer' => [
            'symfony_serializer' => [
                'format' => 'xml',
                'context' => ['default' => 'context'],
            ]
        ],
        'transports' => [
            'default_serializer' => ['dsn' => 'null://'],
            'custom_serializer_short_notation' => [
                'dsn' => 'null://',
                'serializer' => 'messenger.transport.native_php_serializer',
            ],
            'custom_serializer_long_notation' => [
                'dsn' => 'null://',
                'serializer' => [
                    'service_id' => 'messenger.transport.native_php_serializer',
                ],
            ],
            'symfony_serializer_with_context' => [
                'dsn' => 'null://',
                'serializer' => [
                    'format' => 'json',
                    'context' => ['some' => 'context'],
                    'serializer' => 'my_fancy_serializer',
                ],
            ],
            'incoming_message_transport' => [
                'dsn' => 'null://',
                'incoming_message_serializer' => [
                    'messageClass' => BarMessage::class,
                    'format' => 'json',
                    'context' => ['some' => 'context'],
                    'serializer' => 'my_fancy_serializer',
                ],
            ],
            'incoming_message_transport_with_default_serializer' => [
                'dsn' => 'null://',
                'incoming_message_serializer' => [
                    'messageClassResolver' => 'some_message_class_resolver_id',
                ],
            ],
            'outgoing_message_transport' => [
                'dsn' => 'null://',
                'outgoing_message_serializer' => [
                    'format' => 'json',
                    'context' => ['some' => 'context'],
                    'serializer' => 'my_fancy_serializer',
                ],
            ],
            'outgoing_message_transport_with_default_serializer' => [
                'dsn' => 'null://',
                'outgoing_message_serializer' => [],
            ],
        ],
    ],
]);
