<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\Fixtures\\FooReadonly'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\Fixtures\\FooReadonly')),
    ],
    null,
    [
        'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\FooReadonly' => [
            'name' => [
                'k',
            ],
            'value' => [
                'v',
            ],
        ],
    ],
    $o[0],
    []
);
