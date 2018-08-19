<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\Symfony\Component\VarExporter\Tests\MyPrivateValue::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\Symfony\Component\VarExporter\Tests\MyPrivateValue::class, true, true),
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\Symfony\Component\VarExporter\Tests\MyPrivateChildValue::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\Symfony\Component\VarExporter\Tests\MyPrivateChildValue::class, true, true),
    ], [
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\Symfony\Component\VarExporter\Tests\MyPrivateValue::class],
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\Symfony\Component\VarExporter\Tests\MyPrivateChildValue::class],
    ], [
    ]),
    null,
    [
        \Symfony\Component\VarExporter\Tests\MyPrivateValue::class => [
            'prot' => [
                123,
            ],
            'priv' => [
                234,
                234,
            ],
        ],
        \Symfony\Component\VarExporter\Tests\MyPrivateChildValue::class => [
            'prot' => [
                1 => 123,
            ],
        ],
    ],
    [
        \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
        \Symfony\Component\VarExporter\Internal\Registry::$objects[1],
    ],
    []
);
