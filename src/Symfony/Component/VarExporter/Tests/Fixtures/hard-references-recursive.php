<?php

return \Symfony\Component\VarExporter\Internal\Configurator::pop(
    \Symfony\Component\VarExporter\Internal\Registry::push([], [], []),
    [
        \Symfony\Component\VarExporter\Internal\Registry::$references[1] = [
            &\Symfony\Component\VarExporter\Internal\Registry::$references[1],
        ],
    ],
    [],
    [
        &\Symfony\Component\VarExporter\Internal\Registry::$references[1],
    ],
    []
);
