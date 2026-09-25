<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\ArrayObject') || !\class_exists('ArrayObject') ? \deepclone_from_array([
    'classes' => ['Symfony\\Component\\VarExporter\\Tests\\ArrayObject', 'ArrayObject'],
    'objectMeta' => [
        [0, -3],
        [1, -2],
    ],
    'prepared' => 0,
    'states' => [
        2 => [
            1,
            [
                0,
                [],
                [],
                null,
            ],
        ],
        [
            0,
            [
                0,
                [1, 0],
                ['foo' => 1],
                null,
            ],
            [
                1 => [1 => true],
                ['foo' => true],
            ],
        ],
    ],
], null, true) : \unserialize('O:47:"Symfony\\Component\\VarExporter\\Tests\\ArrayObject":4:{i:0;i:0;i:1;a:2:{i:0;i:1;i:1;r:1;}i:2;a:1:{s:3:"foo";O:11:"ArrayObject":4:{i:0;i:0;i:1;a:0:{}i:2;a:0:{}i:3;N;}}i:3;N;}'));
