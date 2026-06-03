<?php

return \deepclone_from_array([
    'classes' => ['Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue', 'Symfony\\Component\\VarExporter\\Tests\\MyPrivateChildValue'],
    'objectMeta' => [0, 1],
    'prepared' => [0, 1],
    'mask' => [true, true],
    'properties' => [
        'Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue' => [
            'prot' => [123, 123],
            'priv' => [234, 234],
        ],
    ],
]);
