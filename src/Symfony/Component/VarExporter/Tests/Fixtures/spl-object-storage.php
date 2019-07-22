<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['SplObjectStorage'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('SplObjectStorage')),
        clone ($p['stdClass'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('stdClass')),
    ],
    null,
    [
        'SplObjectStorage' => [
            "\0" => [
                [
                    $o[1],
                    345,
                ],
            ],
        ],
    ],
    $o[0],
    []
);
