<?php

$container->register('my_connection', \Doctrine\DBAL\Connection::class);

$container->loadFromExtension('framework', [
    'lock' => [
        'foo' => [
            'service_id' => 'my_connection',
            'advisory' => true,
        ],
        'bar' => [
            'service_id' => 'my_connection',
        ],
        'baz' => [
            'flock',
            ['service_id' => 'my_connection', 'advisory' => true],
        ],
    ],
]);
