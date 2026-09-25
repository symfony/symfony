<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\PrivateConstructor') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\PrivateConstructor',
    'objectMeta' => 1,
    'prepared' => 0,
    'properties' => [
        'stdClass' => [
            'prop' => ['bar'],
        ],
    ],
], null, true) : \unserialize('O:54:"Symfony\\Component\\VarExporter\\Tests\\PrivateConstructor":1:{s:4:"prop";s:3:"bar";}'));
