<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes[\stdClass::class] ?? \Symfony\Component\VarExporter\Internal\Registry::p(\stdClass::class, true)),
    ],
    [
        $r = [],
        $r[1] = $o[0],
    ],
    [],
    [
        &$r[1],
        &$r[1],
        $o[0],
    ],
    []
);
