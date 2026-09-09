<?php

$container->loadFromExtension('framework', [
    'messenger' => [
        'transports' => [
            'async' => 'in-memory://',
        ],
    ],
]);
