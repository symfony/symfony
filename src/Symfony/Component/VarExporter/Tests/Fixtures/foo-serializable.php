<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = \Symfony\Component\VarExporter\Internal\Registry::unserialize([], [
        'C:51:"Symfony\\Component\\VarExporter\\Tests\\FooSerializable":20:{a:1:{i:0;s:3:"bar";}}',
    ]),
    null,
    [],
    $o[0],
    []
);
