<?php

$container->loadFromExtension('framework', [
    'messenger' => [
        'default_bus' => 'messenger.bus.commands',
        'buses' => [
            'messenger.bus.commands' => null,
            'messenger.bus.events' => [
                'middleware' => [
                    ['with_factory' => ['foo', true, ['bar' => 'baz']]],
                ],
            ],
            'messenger.bus.queries' => [
                'default_middleware' => false,
                'middleware' => [
                    'send_message',
                    'handle_message',
                ],
            ],
        ],
    ],
]);
