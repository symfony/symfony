<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = \Symfony\Component\VarExporter\Internal\Registry::unserialize([], [
        'C:59:"Symfony\\Component\\VarExporter\\Tests\\Fixtures\\MySerializable":3:{123}',
    ]),
    null,
    [],
    [
        $o[0],
        $o[0],
    ],
    []
);
