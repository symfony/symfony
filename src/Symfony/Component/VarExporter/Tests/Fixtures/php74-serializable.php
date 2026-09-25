<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\Fixtures\\Php74Serializable') || !\class_exists('stdClass') ? \deepclone_from_array([
    'classes' => ['Symfony\\Component\\VarExporter\\Tests\\Fixtures\\Php74Serializable', 'stdClass'],
    'objectMeta' => [
        [0, -2],
        1,
    ],
    'prepared' => 0,
    'states' => [
        2 => [
            0,
            [1],
            [true],
        ],
    ],
], null, true) : \unserialize('O:62:"Symfony\\Component\\VarExporter\\Tests\\Fixtures\\Php74Serializable":1:{i:0;O:8:"stdClass":0:{}}'));
