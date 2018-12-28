<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['DateTime'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DateTime')),
    ],
    null,
    [
        'stdClass' => [
            'date' => [
                0 => '1970-01-01 00:00:00.000000',
            ],
            'timezone_type' => [
                0 => 1,
            ],
            'timezone' => [
                0 => '+00:00',
            ],
        ],
    ],
    $o[0],
    [
        1 => 0,
    ]
);
