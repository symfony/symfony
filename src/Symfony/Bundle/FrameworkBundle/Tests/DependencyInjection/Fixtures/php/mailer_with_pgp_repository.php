<?php

use Symfony\Component\Mailer\EventListener\InMemoryPgpPublicKeyRepository;

$container->register('my_pgp_repository', InMemoryPgpPublicKeyRepository::class)
    ->setArguments([[]]);

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'pgp_encrypter' => [
            'enabled' => true,
            'repository' => 'my_pgp_repository',
        ],
    ],
]);
