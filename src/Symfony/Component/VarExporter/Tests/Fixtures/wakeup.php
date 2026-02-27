<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup')),
        clone $p['Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MyWakeup'],
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
