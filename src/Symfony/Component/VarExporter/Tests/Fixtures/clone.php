<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        (($f = &\Symfony\Component\VarExporter\Internal\Registry::$factories)[\Symfony\Component\VarExporter\Tests\MyCloneable::class] ?? \Symfony\Component\VarExporter\Internal\Registry::f(\Symfony\Component\VarExporter\Tests\MyCloneable::class))(),
        ($f[\Symfony\Component\VarExporter\Tests\MyNotCloneable::class] ?? \Symfony\Component\VarExporter\Internal\Registry::f(\Symfony\Component\VarExporter\Tests\MyNotCloneable::class))(),
    ],
    null,
    [],
    [
        $o[0],
        $o[1],
    ],
    []
);
