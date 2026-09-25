<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup',
    'objectMeta' => [
        [0, 2],
        [0, 1],
    ],
    'prepared' => 0,
    'properties' => [
        'stdClass' => [
            'sub' => [1, 123],
            'baz' => [1 => 123],
        ],
    ],
    'resolve' => [
        'stdClass' => [
            'sub' => [true],
        ],
    ],
    'states' => [1 => 1, 0],
], null, true) : \unserialize('O:53:"Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup":2:{s:3:"sub";O:53:"Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup":2:{s:3:"sub";i:123;s:3:"baz";i:123;}s:3:"baz";N;}'));
