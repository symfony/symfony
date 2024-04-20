<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'envelope' => [
            'sender' => 'sender@example.org',
            'recipients' => ['redirected@example.org', 'redirected1@example.org'],
        ],
        'headers' => [
            'from' => 'from@example.org',
            'bcc' => ['bcc1@example.org', 'bcc2@example.org'],
            'foo' => 'bar',
        ],
    ],
]);
