<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['stdClass'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('stdClass')),
    ],
    null,
    [
        'stdClass' => [
            'foo' => [
                'bar',
            ],
        ],
    ],
    $o[0],
    []
);
