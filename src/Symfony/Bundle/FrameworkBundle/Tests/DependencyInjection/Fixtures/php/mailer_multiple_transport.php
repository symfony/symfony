<?php

$container->loadFromExtension('framework', [
    'mailer' => [
        'transports' => [
            'baz' => 'smtp://baz',
            'foo' => 'smtp://foo',
            'bar' => 'smtp://bar',
        ],
    ],
]);
