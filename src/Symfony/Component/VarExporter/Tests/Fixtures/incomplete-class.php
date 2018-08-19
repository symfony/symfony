<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([], [], [
        'O:20:"SomeNotExistingClass":0:{}',
    ]),
    null,
    [],
    \Symfony\Component\VarExporter\Internal\Registry::$objects[0],
    []
);
