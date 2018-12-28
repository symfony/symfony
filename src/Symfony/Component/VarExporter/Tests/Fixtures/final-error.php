<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = \Symfony\Component\VarExporter\Internal\Registry::unserialize([], [
        0 => 'O:46:"Symfony\\Component\\VarExporter\\Tests\\FinalError":1:{s:12:"'."\0".'Error'."\0".'trace";a:0:{}}',
    ]),
    null,
    [
        'TypeError' => [
            'file' => [
                0 => \dirname(__DIR__).\DIRECTORY_SEPARATOR.'VarExporterTest.php',
            ],
            'line' => [
                0 => 123,
            ],
        ],
    ],
    $o[0],
    [
        1 => 0,
    ]
);
