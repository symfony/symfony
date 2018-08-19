<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\Symfony\Component\VarExporter\Tests\MyWakeup::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\Symfony\Component\VarExporter\Tests\MyWakeup::class, true, true),
    ], [
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\Symfony\Component\VarExporter\Tests\MyWakeup::class],
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\Symfony\Component\VarExporter\Tests\MyWakeup::class],
    ], [
    ]),
    null,
    [
        \Symfony\Component\VarExporter\Tests\MyWakeup::class => [
            'sub' => [
                \Symfony\Component\VarExporter\Internal\Registry::$objects[1],
                123,
            ],
            'baz' => [
                1 => 123,
            ],
        ],
    ],
    \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    [
        1 => 1,
        2 => 0,
    ]
);
