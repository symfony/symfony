<?php

$container->loadFromExtension('framework', [
    'messenger' => [
        'reject_redelivered_messages' => false,
        'default_bus' => 'messenger.bus.default',
        'buses' => [
            'messenger.bus.default' => null,
            'messenger.bus.commands' => [
                'middleware' => ['reject_redelivered_message_middleware'],
            ],
        ],
    ],
]);
