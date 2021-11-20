<?php

use Symfony\Config\ScalarNormalizedTypesConfig;

return static function (ScalarNormalizedTypesConfig $config) {
    $config
        ->simpleArray('foo')
        ->keyedArray('key', 'value')
        ->listObject('bar')
        ->listObject('baz')
        ->listObject()->name('qux');
};
