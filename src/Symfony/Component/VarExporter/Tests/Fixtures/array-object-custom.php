<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes[\Symfony\Component\VarExporter\Tests\MyArrayObject::class] ?? \Symfony\Component\VarExporter\Internal\Registry::p(\Symfony\Component\VarExporter\Tests\MyArrayObject::class)),
    ],
    null,
    [
        \ArrayObject::class => [
            "\0" => [
                [
                    [
                        234,
                    ],
                    1,
                ],
            ],
        ],
    ],
    $o[0],
    []
);
