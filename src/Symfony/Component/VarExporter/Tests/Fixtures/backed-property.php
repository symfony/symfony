<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\Fixtures\\BackedProperty'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\Fixtures\\BackedProperty')),
    ],
    null,
    [
        'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\BackedProperty' => [
            'name' => [
                'name',
            ],
        ],
    ],
    $o[0],
    []
);
