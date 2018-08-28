<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p =& \Symfony\Component\VarExporter\Internal\Registry::$prototypes)[\Symfony\Component\VarExporter\Tests\MyPrivateValue::class] ?? \Symfony\Component\VarExporter\Internal\Registry::p(\Symfony\Component\VarExporter\Tests\MyPrivateValue::class, true)),
        clone ($p[\Symfony\Component\VarExporter\Tests\MyPrivateChildValue::class] ?? \Symfony\Component\VarExporter\Internal\Registry::p(\Symfony\Component\VarExporter\Tests\MyPrivateChildValue::class, true)),
    ],
    null,
    [
        \Symfony\Component\VarExporter\Tests\MyPrivateValue::class => [
            'prot' => [
                123,
                123,
            ],
            'priv' => [
                234,
                234,
            ],
        ],
    ],
    [
        $o[0],
        $o[1],
    ],
    []
);
