<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$container->loadFromExtension('framework', [
    'rate_limiter' => [
        'foo_limiter' => [
            'lock_factory' => null,
            'policy' => 'token_bucket',
            'limit' => 10,
            'rate' => ['interval' => '5 seconds', 'amount' => 10],
        ],
    ],
    'mailer' => [
        'transports' => [
            'main' => [
                'dsn' => 'smtp://example.com',
                'rate_limiter' => 'foo_limiter',
            ],
        ],
    ],
]);
