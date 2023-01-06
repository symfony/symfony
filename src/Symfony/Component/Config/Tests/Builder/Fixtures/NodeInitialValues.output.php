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
    'some_clever_name' => [
        'first' => 'bar',
        'second' => 'foo',
        'third' => null,
    ],
    'messenger' => [
        'transports' => [
            'fast_queue' => [
                'dsn' => 'sync://',
                'serializer' => 'acme',
            ],
            'slow_queue' => [
                'dsn' => 'doctrine://',
                'options' => ['table' => 'my_messages'],
            ],
        ],
    ],
];
