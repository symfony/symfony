<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Config\ArrayExtraKeysConfig;

return static function (ArrayExtraKeysConfig $config) {
    $config->foo([
        'extra1' => 'foo_extra1',
    ])
        ->baz('foo_baz')
        ->qux('foo_qux')
        ->set('extra2', 'foo_extra2')
        ->set('extra3', 'foo_extra3');

    $config->bar([
        'extra1' => 'bar1_extra1',
    ])
        ->corge('bar1_corge')
        ->grault('bar1_grault')
        ->set('extra2', 'bar1_extra2')
        ->set('extra3', 'bar1_extra3');

    $config->bar([
        'extra1' => 'bar2_extra1',
        'extra4' => 'bar2_extra4',
    ])
        ->corge('bar2_corge')
        ->grault('bar2_grault')
        ->set('extra2', 'bar2_extra2')
        ->set('extra3', 'bar2_extra3')
        ->set('extra4', null);
};
