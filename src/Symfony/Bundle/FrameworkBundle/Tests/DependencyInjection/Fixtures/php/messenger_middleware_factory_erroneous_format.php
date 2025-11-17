<?php

$container->loadFromExtension('framework', [
    'messenger' => [
        'buses' => [
            'command_bus' => [
                'middleware' => [
                    [
                        'foo' => ['qux'],
                        'bar' => ['baz'],
                    ],
                ],
            ],
        ],
    ],
]);
