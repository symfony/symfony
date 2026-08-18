<?php

use Symfony\Component\Mailer\EventListener\InMemorySmimeCertificateRepository;

$container->register('my_repository', InMemorySmimeCertificateRepository::class)
    ->setArguments([[]]);

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'smime_encrypter' => [
            'enabled' => true,
            'repository' => 'my_repository',
        ],
    ],
]);
