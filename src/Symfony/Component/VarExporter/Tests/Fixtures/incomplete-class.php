<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = \Symfony\Component\VarExporter\Internal\Registry::unserialize([], [
        0 => 'O:20:"SomeNotExistingClass":0:{}',
    ]),
    null,
    [],
    $o[0],
    []
);
