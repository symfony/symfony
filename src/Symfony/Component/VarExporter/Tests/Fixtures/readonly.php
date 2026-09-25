<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\Fixtures\\FooReadonly') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\FooReadonly',
    'objectMeta' => 1,
    'prepared' => 0,
    'properties' => [
        'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\FooReadonly' => [
            'name' => ['k'],
            'value' => ['v'],
        ],
    ],
], null, true) : \unserialize('O:56:"Symfony\\Component\\VarExporter\\Tests\\Fixtures\\FooReadonly":2:{s:4:"name";s:1:"k";s:5:"value";s:1:"v";}'));
