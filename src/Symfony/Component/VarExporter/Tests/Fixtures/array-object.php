<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p =& \Symfony\Component\VarExporter\Internal\Registry::$prototypes)[\ArrayObject::class] ?? \Symfony\Component\VarExporter\Internal\Registry::p(\ArrayObject::class, true)),
        clone $p[\ArrayObject::class],
    ],
    null,
    [
        \ArrayObject::class => [
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
        '*' => [
            'foo' => [
                $o[1],
            ],
        ],
    ],
    $o[0],
    []
);
