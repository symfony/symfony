<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes[\Symfony\Component\VarExporter\Tests\GoodNight::class] ?? \Symfony\Component\VarExporter\Internal\Registry::p(\Symfony\Component\VarExporter\Tests\GoodNight::class, true)),
    ],
    null,
    [
        '*' => [
            'good' => [
                'night',
            ],
        ],
    ],
    $o[0],
    []
);
