<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\ArrayObject::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\ArrayObject::class, true, true),
    ], [
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\ArrayObject::class],
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\ArrayObject::class],
    ], [
    ]),
    null,
    [
        \ArrayObject::class => [
            "\0" => [
                [
                    [
                        1,
                        \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
                    ],
                    0,
                ],
            ],
            'foo' => [
                \Symfony\Component\VarExporter\Internal\Registry::$objects[1],
            ],
        ],
    ],
    \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    []
);
