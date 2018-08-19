<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\DateTime::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\DateTime::class, true, true),
    ], [
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\DateTime::class],
    ], [
    ]),
    null,
    [
        \DateTime::class => [
            'date' => [
                '1970-01-01 00:00:00.000000',
            ],
            'timezone_type' => [
                1,
            ],
            'timezone' => [
                '+00:00',
            ],
        ],
    ],
    \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    [
        1 => 0,
    ]
);
