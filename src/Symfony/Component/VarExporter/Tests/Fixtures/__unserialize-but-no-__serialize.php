<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\__UnserializeButNo__Serialize') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\__UnserializeButNo__Serialize',
    'objectMeta' => [
        [0, -1],
    ],
    'prepared' => 0,
    'states' => [
        1 => [
            0,
            ['foo' => 'ccc'],
        ],
    ],
], null, true) : \unserialize('O:65:"Symfony\\Component\\VarExporter\\Tests\\__UnserializeButNo__Serialize":1:{s:3:"foo";s:3:"ccc";}'));
