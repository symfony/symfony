<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'transports' => [
        'foo' => [
            'dsn' => 'bar',
        ],
        'bar' => [
            'dsn' => 'foobar',
        ],
    ],
    'error_pages' => [
        'with_trace' => false,
    ]
];
