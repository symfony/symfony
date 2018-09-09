<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        (\Symfony\Component\VarExporter\Internal\Registry::$factories[\Symfony\Component\VarExporter\Tests\FinalStdClass::class] ?? \Symfony\Component\VarExporter\Internal\Registry::f(\Symfony\Component\VarExporter\Tests\FinalStdClass::class))(),
    ],
    null,
    [],
    $o[0],
    []
);
