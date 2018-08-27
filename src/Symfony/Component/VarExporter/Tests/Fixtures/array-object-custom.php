<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\Symfony\Component\VarExporter\Tests\MyArrayObject::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\Symfony\Component\VarExporter\Tests\MyArrayObject::class, true, true),
    ], [
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\Symfony\Component\VarExporter\Tests\MyArrayObject::class],
    ], [
    ]),
    null,
    [
        \ArrayObject::class => [
            "\0" => [
                [
                    [
                        234,
                    ],
                    1,
                ],
            ],
        ],
    ],
    \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    []
);
