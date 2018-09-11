<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['ArrayIterator'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('ArrayIterator')),
    ],
    null,
    [
        'ArrayIterator' => [
            "\0" => [
                [
                    [
                        123,
                    ],
                    1,
                ],
            ],
        ],
    ],
    $o[0],
    []
);
