<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        (\Symfony\Component\VarExporter\Internal\Registry::$factories['Symfony\\Component\\VarExporter\\Tests\\FinalError'] ?? \Symfony\Component\VarExporter\Internal\Registry::f('Symfony\\Component\\VarExporter\\Tests\\FinalError'))(),
    ],
    null,
    [
        'TypeError' => [
            'file' => [
                \dirname(__DIR__).\DIRECTORY_SEPARATOR.'VarExporterTest.php',
            ],
            'line' => [
                123,
            ],
        ],
        'Error' => [
            'trace' => [
                [],
            ],
        ],
    ],
    $o[0],
    [
        1 => 0,
    ]
);
