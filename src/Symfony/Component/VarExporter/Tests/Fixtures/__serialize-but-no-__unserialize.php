<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\__SerializeButNo__Unserialize') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\__SerializeButNo__Unserialize',
    'objectMeta' => 1,
    'prepared' => 0,
    'properties' => [
        'Symfony\\Component\\VarExporter\\Tests\\ParentOf__SerializeButNo__Unserialize' => [
            'foo' => ['foo'],
        ],
        'stdClass' => [
            'baz' => ['ccc'],
        ],
        'Symfony\\Component\\VarExporter\\Tests\\__SerializeButNo__Unserialize' => [
            'bar' => ['ddd'],
        ],
    ],
], null, true) : \unserialize('O:65:"Symfony\\Component\\VarExporter\\Tests\\__SerializeButNo__Unserialize":3:{s:3:"foo";s:3:"foo";s:3:"baz";s:3:"ccc";s:3:"bar";s:3:"ddd";}'));
