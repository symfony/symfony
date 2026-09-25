<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\FinalStdClass') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\FinalStdClass',
    'objectMeta' => 1,
    'prepared' => 0,
], null, true) : \unserialize('O:49:"Symfony\\Component\\VarExporter\\Tests\\FinalStdClass":0:{}'));
