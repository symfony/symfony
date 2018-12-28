<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = \Symfony\Component\VarExporter\Internal\Registry::unserialize([], [
        0 => 'C:50:"Symfony\\Component\\VarExporter\\Tests\\MySerializable":3:{123}',
    ]),
    null,
    [],
    [
        0 => $o[0],
        1 => $o[0],
    ],
    []
);
