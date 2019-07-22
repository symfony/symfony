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
                [
                    [
                        1,
                        $o[0],
                    ],
                    0,
                ],
            ],
        ],
        'stdClass' => [
            'foo' => [
                $o[1],
            ],
        ],
    ],
    $o[0],
    []
);
