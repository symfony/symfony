<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\Symfony\Component\VarExporter\Tests\MyCloneable::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\Symfony\Component\VarExporter\Tests\MyCloneable::class, true, false),
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\Symfony\Component\VarExporter\Tests\MyNotCloneable::class] ?? \Symfony\Component\VarExporter\Internal\Registry::getClassReflector(\Symfony\Component\VarExporter\Tests\MyNotCloneable::class, true, false),
    ], [
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\Symfony\Component\VarExporter\Tests\MyCloneable::class]->newInstanceWithoutConstructor(),
        \Symfony\Component\VarExporter\Internal\Registry::$reflectors[\Symfony\Component\VarExporter\Tests\MyNotCloneable::class]->newInstanceWithoutConstructor(),
    ], [
    ]),
    null,
    [],
    [
        \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
        \Symfony\Component\VarExporter\Internal\Registry::$objects[1],
    ],
    []
);
