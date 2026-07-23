<?php

return \deepclone_from_array([
    'classes' => 'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup',
    'objectMeta' => [
        [0, 2],
        [0, 1],
    ],
    'prepared' => 0,
    'properties' => [
        'stdClass' => [
            'sub' => [1, 123],
            'baz' => [1 => 123],
        ],
    ],
    'resolve' => [
        'stdClass' => [
            'sub' => [true],
        ],
    ],
    'states' => [1 => 1, 0],
], null, true);
