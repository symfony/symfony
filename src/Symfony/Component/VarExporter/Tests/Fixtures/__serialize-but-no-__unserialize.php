<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\__SerializeButNo__Unserialize'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\__SerializeButNo__Unserialize')),
    ],
    null,
    [
        'Symfony\\Component\\VarExporter\\Tests\\ParentOf__SerializeButNo__Unserialize' => [
            'foo' => [
                'foo',
            ],
        ],
        'stdClass' => [
            'baz' => [
                'ccc',
            ],
        ],
        'Symfony\\Component\\VarExporter\\Tests\\__SerializeButNo__Unserialize' => [
            'bar' => [
                'ddd',
            ],
        ],
    ],
    $o[0],
    []
);
