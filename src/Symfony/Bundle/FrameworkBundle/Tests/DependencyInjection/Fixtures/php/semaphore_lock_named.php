<?php

$container->loadFromExtension('framework', [
    'lock' => [
        'default' => 'flock',
        'foo' => 'flock',
    ],
    'semaphore' => [
        'default' => 'lock://',
        'bar' => 'lock://foo',
    ],
]);
