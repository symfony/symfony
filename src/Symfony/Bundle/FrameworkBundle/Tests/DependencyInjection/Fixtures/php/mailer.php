<?php

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'envelope' => [
            'sender' => 'sender@example.org',
            'recipients' => ['redirected@example.org', 'redirected1@example.org'],
        ],
    ],
]);
