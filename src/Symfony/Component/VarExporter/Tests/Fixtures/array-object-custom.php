<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\MyArrayObject'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\MyArrayObject')),
    ],
    null,
    [],
    $o[0],
    [
        [
            1,
            [
                234,
            ],
            [
                "\0".'Symfony\\Component\\VarExporter\\Tests\\MyArrayObject'."\0".'unused' => 123,
            ],
            null,
        ],
    ]
);
