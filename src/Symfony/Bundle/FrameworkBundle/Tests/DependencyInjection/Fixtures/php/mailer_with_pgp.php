<?php

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'pgp_signer' => [
            'enabled' => true,
            'secret_key' => '/path/to/secret.asc',
            'public_key' => '/path/to/public.asc',
            'passphrase' => 'passphrase',
            'digest_algorithm' => 'SHA256',
        ],
        'pgp_encrypter' => [
            'enabled' => true,
            'keys' => [
                'r1@example.com' => '/path/to/r1.asc',
                'r2@example.com' => '/path/to/r2.asc',
            ],
            'cipher_algorithm' => 'AES192',
            'hide_recipients' => true,
            'on_missing_key' => 'skip',
            'encrypt_for_sender' => true,
        ],
    ],
]);
