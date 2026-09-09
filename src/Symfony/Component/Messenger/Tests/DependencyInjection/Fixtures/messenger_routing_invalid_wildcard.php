<?php

$container->loadFromExtension('messenger', [
    'serializer' => [
        'default_serializer' => 'messenger.transport.symfony_serializer',
    ],
    'routing' => [
        'Symfony\*\DummyMessage' => ['audit'],
    ],
    'transports' => [
        'audit' => 'null://',
    ],
]);
