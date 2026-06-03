<?php

$container->loadFromExtension('framework', [
    'serializer' => true,
    'messenger' => [
        'serializer' => [
            'default_serializer' => 'messenger.transport.symfony_serializer',
        ],
        'routing' => [
            'Symfony\*\DummyMessage' => ['audit'],
        ],
        'transports' => [
            'audit' => 'null://',
        ],
    ],
]);
