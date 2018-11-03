<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\GoodNight'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\GoodNight')),
    ],
    null,
    [
        'stdClass' => [
            'good' => [
                'night',
            ],
        ],
    ],
    $o[0],
    []
);
