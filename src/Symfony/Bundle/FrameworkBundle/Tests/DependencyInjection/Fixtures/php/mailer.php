<?php

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
    ],
]);
