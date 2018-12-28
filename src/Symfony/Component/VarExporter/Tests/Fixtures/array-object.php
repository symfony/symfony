<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['ArrayObject'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('ArrayObject')),
        clone $p['ArrayObject'],
    ],
    null,
    [
        'ArrayObject' => [
            "\0" => [
                0 => [
                    0 => [
                        0 => 1,
                        1 => $o[0],
                    ],
                    1 => 0,
                ],
            ],
        ],
        'stdClass' => [
            'foo' => [
                0 => $o[1],
            ],
        ],
    ],
    $o[0],
    []
);
