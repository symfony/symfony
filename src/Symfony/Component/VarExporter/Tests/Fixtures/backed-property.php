<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\Fixtures\\BackedProperty') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\BackedProperty',
    'objectMeta' => 1,
    'prepared' => 0,
    'properties' => [
        'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\BackedProperty' => [
            'name' => ['name'],
        ],
    ],
], null, true) : \unserialize('O:59:"Symfony\\Component\\VarExporter\\Tests\\Fixtures\\BackedProperty":1:{s:4:"name";s:4:"name";}'));
