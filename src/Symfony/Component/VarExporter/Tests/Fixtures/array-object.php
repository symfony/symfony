<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['ArrayObject'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('ArrayObject')),
        clone $p['ArrayObject'],
    ],
    null,
    [],
    $o[0],
    [
        [
            0,
            [
                1,
                $o[0],
            ],
            [
                'foo' => $o[1],
            ],
            null,
        ],
        -1 => [
            0,
            [],
            [],
            null,
        ],
    ]
);
