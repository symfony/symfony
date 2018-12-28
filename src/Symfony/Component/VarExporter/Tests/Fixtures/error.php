<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        (\Symfony\Component\VarExporter\Internal\Registry::$factories['Error'] ?? \Symfony\Component\VarExporter\Internal\Registry::f('Error'))(),
    ],
    null,
    [
        'TypeError' => [
            'file' => [
                0 => \dirname(__DIR__).\DIRECTORY_SEPARATOR.'VarExporterTest.php',
            ],
            'line' => [
                0 => 234,
            ],
        ],
        'Error' => [
            'trace' => [
                0 => [
                    'file' => \dirname(__DIR__).\DIRECTORY_SEPARATOR.'VarExporterTest.php',
                    'line' => 123,
                ],
            ],
        ],
    ],
    $o[0],
    [
        1 => 0,
    ]
);
