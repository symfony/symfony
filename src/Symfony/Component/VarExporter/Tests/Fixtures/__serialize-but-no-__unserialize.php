<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\__SerializeButNo__Unserialize'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\__SerializeButNo__Unserialize')),
    ],
    null,
    [
        'stdClass' => [
            'foo' => [
                'ccc',
            ],
        ],
    ],
    $o[0],
    []
);
