<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\MyPrivateChildValue') ? \deepclone_from_array([
    'classes' => ['Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue', 'Symfony\\Component\\VarExporter\\Tests\\MyPrivateChildValue'],
    'objectMeta' => [0, 1],
    'prepared' => [0, 1],
    'mask' => [true, true],
    'properties' => [
        'Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue' => [
            'prot' => [123, 123],
            'priv' => [234, 234],
        ],
    ],
], null, true) : \unserialize('a:2:{i:0;O:50:"Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue":2:{s:7:"'."\0".'*'."\0".'prot";i:123;s:56:"'."\0".'Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue'."\0".'priv";i:234;}i:1;O:55:"Symfony\\Component\\VarExporter\\Tests\\MyPrivateChildValue":2:{s:7:"'."\0".'*'."\0".'prot";i:123;s:56:"'."\0".'Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue'."\0".'priv";i:234;}}'));
