<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\__UnserializeButNo__Serialize'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\__UnserializeButNo__Serialize')),
    ],
    null,
    [],
    $o[0],
    [
        [
            'foo' => 'ccc',
        ],
    ]
);
