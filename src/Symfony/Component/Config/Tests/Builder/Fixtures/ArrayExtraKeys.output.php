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
    'foo' => [
        'baz' => 'foo_baz',
        'qux' => 'foo_qux',
        'extra1' => 'foo_extra1',
        'extra2' => 'foo_extra2',
        'extra3' => 'foo_extra3',
    ],
    'bar' => [
        [
            'corge' => 'bar1_corge',
            'grault' => 'bar1_grault',
            'extra1' => 'bar1_extra1',
            'extra2' => 'bar1_extra2',
            'extra3' => 'bar1_extra3',
        ],
        [
            'corge' => 'bar2_corge',
            'grault' => 'bar2_grault',
            'extra1' => 'bar2_extra1',
            'extra4' => null,
            'extra2' => 'bar2_extra2',
            'extra3' => 'bar2_extra3',
        ],
    ],
];
