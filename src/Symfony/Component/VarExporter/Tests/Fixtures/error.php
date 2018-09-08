<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        (\Symfony\Component\VarExporter\Internal\Registry::$factories[\Error::class] ?? \Symfony\Component\VarExporter\Internal\Registry::f(\Error::class))(),
    ],
    null,
    [
        \TypeError::class => [
            'file' => [
                \dirname(__DIR__).\DIRECTORY_SEPARATOR.'VarExporterTest.php',
            ],
            'line' => [
                234,
            ],
        ],
        \Error::class => [
            'trace' => [
                [
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
