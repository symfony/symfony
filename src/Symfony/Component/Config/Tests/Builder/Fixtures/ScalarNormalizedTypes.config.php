<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Config\ScalarNormalizedTypesConfig;

return static function (ScalarNormalizedTypesConfig $config) {
    $config
        ->simpleArray('foo')
        ->keyedArray('key', 'value')
        ->object(true)
        ->listObject('bar')
        ->listObject('baz')
        ->listObject()->name('qux');

    $config
        ->keyedListObject('Foo\\Bar', true)
        ->keyedListObject('Foo\\Baz')->settings(['one', 'two']);

    $config->nested([
        'nested_object' => true,
        'nested_list_object' => ['one', 'two'],
    ]);
};
