<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\ArrayIterator::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\ArrayIterator::class, true, true),
    ], [
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\ArrayIterator::class],
    ], [
    ]),
    null,
    [
        \ArrayIterator::class => [
            "\0" => [
                [
                    [
                        123,
                    ],
                    1,
                ],
            ],
        ],
    ],
    \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    []
);
