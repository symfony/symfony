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
    'translator' => [
        'fallbacks' => ['sv', 'fr', 'es'],
        'sources' => [
            '\\Acme\\Foo' => 'yellow',
            '\\Acme\\Bar' => 'green',
        ],
    ],
    'messenger' => [
        'routing' => [
            'Foo\\MyArrayMessage' => ['senders' => ['workqueue']],
            'Foo\\Message' => ['senders' => ['workqueue']],
            'Foo\\DoubleMessage' => ['senders' => ['sync', 'workqueue']],
        ],
        'receiving' => [
            ['priority' => 10, 'color' => 'blue'],
            ['priority' => 5, 'color' => 'red'],
        ],
    ],
];
