<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue')),
        clone ($p['Symfony\\Component\\VarExporter\\Tests\\MyPrivateChildValue'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\MyPrivateChildValue')),
    ],
    null,
    [
        'Symfony\\Component\\VarExporter\\Tests\\MyPrivateValue' => [
            'prot' => [
                123,
            ],
            'priv' => [
                234,
                234,
            ],
        ],
        'Symfony\\Component\\VarExporter\\Tests\\MyPrivateChildValue' => [
            'prot' => [
                1 => 123,
            ],
        ],
    ],
    [
        $o[0],
        $o[1],
    ],
    []
);
