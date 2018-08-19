<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\SplObjectStorage::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\SplObjectStorage::class, true, true),
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\stdClass::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\stdClass::class, true, true),
    ], [
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\SplObjectStorage::class],
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\stdClass::class],
    ], [
    ]),
    null,
    [
        \SplObjectStorage::class => [
            "\0" => [
                [
                    \Symfony\Component\VarExporter\Internal\Registry::$objects[1],
                    345,
                ],
            ],
        ],
    ],
    \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    []
);
