<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\MyArrayObject') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\MyArrayObject',
    'objectMeta' => [
        [0, -1],
    ],
    'prepared' => 0,
    'states' => [
        1 => [
            0,
            [
                1,
                [234],
                ["\0".'Symfony\\Component\\VarExporter\\Tests\\MyArrayObject'."\0".'unused' => 123],
                null,
            ],
        ],
    ],
], null, true) : \unserialize('O:49:"Symfony\\Component\\VarExporter\\Tests\\MyArrayObject":4:{i:0;i:1;i:1;a:1:{i:0;i:234;}i:2;a:1:{s:57:"'."\0".'Symfony\\Component\\VarExporter\\Tests\\MyArrayObject'."\0".'unused";i:123;}i:3;N;}'));
