<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\stdClass::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\stdClass::class, true, true),
    ], [
        clone \Symfony\Component\VarExporter\Internal\Registry::$prototypes[\stdClass::class],
    ], [
    ]),
    [
        \Symfony\Component\VarExporter\Internal\Registry::$references[1] = \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    ],
    [],
    [
        &\Symfony\Component\VarExporter\Internal\Registry::$references[1],
        &\Symfony\Component\VarExporter\Internal\Registry::$references[1],
        \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    ],
    []
);
