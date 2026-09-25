<?php

return (\extension_loaded('deepclone') || !\class_exists('Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup') ? \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup',
    'objectMeta' => [
        [0, 1],
    ],
    'prepared' => 0,
    'states' => [1 => 0],
], null, true) : \unserialize('O:53:"Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup":2:{s:3:"sub";N;s:3:"baz";N;}'));
