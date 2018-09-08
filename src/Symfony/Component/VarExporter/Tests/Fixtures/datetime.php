<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes[\DateTime::class] ?? \Symfony\Component\VarExporter\Internal\Registry::p(\DateTime::class)),
    ],
    null,
    [
        '*' => [
            'date' => [
                '1970-01-01 00:00:00.000000',
            ],
            'timezone_type' => [
                1,
            ],
            'timezone' => [
                '+00:00',
            ],
        ],
    ],
    $o[0],
    [
        1 => 0,
    ]
);
