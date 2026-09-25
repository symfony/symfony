<?php

return (\extension_loaded('deepclone') || !\class_exists('SplObjectStorage') || !\class_exists('stdClass') ? \deepclone_from_array([
    'classes' => ['SplObjectStorage', 'stdClass'],
    'objectMeta' => [
        [0, -2],
        1,
    ],
    'prepared' => 0,
    'states' => [
        2 => [
            0,
            [
                [1, 345],
                [],
            ],
            [
                [true],
            ],
        ],
    ],
], null, true) : \unserialize('O:16:"SplObjectStorage":2:{i:0;a:2:{i:0;O:8:"stdClass":0:{}i:1;i:345;}i:1;a:0:{}}'));
