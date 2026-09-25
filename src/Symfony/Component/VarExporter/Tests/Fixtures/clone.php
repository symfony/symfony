<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\MyCloneable') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\MyNotCloneable') ? \deepclone_from_array([
    'classes' => ['Symfony\\Component\\VarExporter\\Tests\\MyCloneable', 'Symfony\\Component\\VarExporter\\Tests\\MyNotCloneable'],
    'objectMeta' => [0, 1],
    'prepared' => [0, 1],
    'mask' => [true, true],
], null, true) : \unserialize('a:2:{i:0;O:47:"Symfony\\Component\\VarExporter\\Tests\\MyCloneable":0:{}i:1;O:50:"Symfony\\Component\\VarExporter\\Tests\\MyNotCloneable":0:{}}'));
