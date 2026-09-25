<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\ConcreteClass') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\ConcreteClass',
    'objectMeta' => 1,
    'prepared' => 0,
    'properties' => [
        'Symfony\\Component\\VarExporter\\Tests\\AbstractClass' => [
            'foo' => [123],
            'bar' => [234],
        ],
    ],
], null, true) : \unserialize('O:49:"Symfony\\Component\\VarExporter\\Tests\\ConcreteClass":2:{s:6:"'."\0".'*'."\0".'foo";i:123;s:54:"'."\0".'Symfony\\Component\\VarExporter\\Tests\\AbstractClass'."\0".'bar";i:234;}'));
