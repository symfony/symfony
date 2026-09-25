<?php

return (\extension_loaded('deepclone') || !\class_exists('stdClass') ? \deepclone_from_array([
    'classes' => 'stdClass',
    'objectMeta' => 1,
    'prepared' => [-1, -1, 0],
    'mask' => [false, false, true],
    'refs' => [1 => 0],
    'refMasks' => [1 => true],
], null, true) : \unserialize('a:3:{i:0;O:8:"stdClass":0:{}i:1;R:2;i:2;r:2;}'));
