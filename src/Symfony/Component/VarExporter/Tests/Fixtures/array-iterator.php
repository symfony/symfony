<?php

return (\extension_loaded('deepclone') || !\class_exists('ArrayIterator') ? \deepclone_from_array([
    'classes' => 'ArrayIterator',
    'objectMeta' => [
        [0, -1],
    ],
    'prepared' => 0,
    'states' => [
        1 => [
            0,
            [
                1,
                [123],
                [],
                null,
            ],
        ],
    ],
], null, true) : \unserialize('O:13:"ArrayIterator":4:{i:0;i:1;i:1;a:1:{i:0;i:123;}i:2;a:0:{}i:3;N;}'));
