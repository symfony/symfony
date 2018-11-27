<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['Symfony\\Component\\VarExporter\\Tests\\MyWakeup'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\MyWakeup')),
        clone $p['Symfony\\Component\\VarExporter\\Tests\\MyWakeup'],
    ],
    null,
    [
        'stdClass' => [
            'sub' => [
                $o[1],
                123,
            ],
            'baz' => [
                1 => 123,
            ],
        ],
    ],
    $o[0],
    [
        1 => 1,
        0,
    ]
);
