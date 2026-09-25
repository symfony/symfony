<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\FinalArrayIterator') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\FinalArrayIterator',
    'objectMeta' => [
        [0, -1],
    ],
    'prepared' => 0,
    'states' => [
        1 => [
            0,
            [
                0,
                [],
                [],
                null,
            ],
        ],
    ],
], null, true) : \unserialize('O:54:"Symfony\\Component\\VarExporter\\Tests\\FinalArrayIterator":4:{i:0;i:0;i:1;a:0:{}i:2;a:0:{}i:3;N;}'));
