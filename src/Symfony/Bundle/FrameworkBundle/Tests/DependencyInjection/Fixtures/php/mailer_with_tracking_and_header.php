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
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'headers' => [
            'X-Track' => 'opens=true; clicks=default',
        ],
        'tracking' => [
            'opens' => false,
            'clicks' => false,
        ],
    ],
]);
