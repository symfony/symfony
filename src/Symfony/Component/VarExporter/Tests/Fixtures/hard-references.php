<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['stdClass'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('stdClass')),
    ],
    [
        $r = [],
        $r[1] = $o[0],
    ],
    [],
    [
        0 => &$r[1],
        1 => &$r[1],
        2 => $o[0],
    ],
    []
);
