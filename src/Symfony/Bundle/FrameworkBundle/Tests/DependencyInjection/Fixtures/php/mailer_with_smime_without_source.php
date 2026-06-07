<?php

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'smime_encrypter' => [
            'enabled' => true,
        ],
    ],
]);
