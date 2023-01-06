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
    'simple_array' => 'foo',
    'keyed_array' => [
        'key' => 'value',
    ],
    'object' => true,
    'list_object' => [
        'bar',
        'baz',
        ['name' => 'qux'],
    ],
    'keyed_list_object' => [
        'Foo\\Bar' => true,
        'Foo\\Baz' => [
            'settings' => ['one', 'two'],
        ],
    ],
    'nested' => [
        'nested_object' => true,
        'nested_list_object' => ['one', 'two'],
    ],
];
