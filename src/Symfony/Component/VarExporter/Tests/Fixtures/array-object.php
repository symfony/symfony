<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['Symfony\\Component\\VarExporter\\Tests\\ArrayObject'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\ArrayObject')),
        clone ($p['ArrayObject'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('ArrayObject')),
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
