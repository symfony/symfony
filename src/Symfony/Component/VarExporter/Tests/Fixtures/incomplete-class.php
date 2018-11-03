<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = \Symfony\Component\VarExporter\Internal\Registry::unserialize([], [
        'O:20:"SomeNotExistingClass":0:{}',
    ]),
    null,
    [],
    $o[0],
    []
);
