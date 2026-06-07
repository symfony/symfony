<?php

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'smime_encrypter' => [
            'enabled' => true,
            'certificates' => [
                'r1@example.com' => '/path/to/r1.crt',
                'r2@example.com' => '/path/to/r2.crt',
            ],
            'on_missing_certificate' => 'fail',
            'encrypt_for_sender' => true,
        ],
    ],
]);
