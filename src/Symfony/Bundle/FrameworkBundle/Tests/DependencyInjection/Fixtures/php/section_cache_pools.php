<?php

$container->loadFromExtension('framework', [
    'scheduler' => true,
    'messenger' => [
        'transports' => [
            'sender.foo' => 'null://',
        ],
    ],
]);
