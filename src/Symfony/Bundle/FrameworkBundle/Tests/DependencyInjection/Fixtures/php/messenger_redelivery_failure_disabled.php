<?php

$container->loadFromExtension('framework', [
    'serializer' => true,
    'messenger' => [
        'transports' => [
            'example' => 'redis://127.0.0.1:6379/messages',
        ],
    ],
]);
