<?php

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'pgp_signer' => [
            'enabled' => true,
        ],
    ],
]);
