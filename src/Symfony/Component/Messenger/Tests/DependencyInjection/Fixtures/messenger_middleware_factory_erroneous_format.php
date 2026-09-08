<?php

$container->loadFromExtension('messenger', [
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
]);
