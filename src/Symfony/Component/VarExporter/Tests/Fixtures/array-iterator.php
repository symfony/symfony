<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['ArrayIterator'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('ArrayIterator')),
    ],
    null,
    [],
    $o[0],
    [
        [
            1,
            [
                123,
            ],
            [],
            null,
        ],
    ]
);
