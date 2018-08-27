<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([], [], [
        'C:50:"Symfony\\Component\\VarExporter\\Tests\\MySerializable":3:{123}',
    ]),
    null,
    [],
    [
        \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
        \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    ],
    []
);
