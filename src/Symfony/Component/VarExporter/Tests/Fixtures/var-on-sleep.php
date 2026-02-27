<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\Fixtures\\GoodNight'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\Fixtures\\GoodNight')),
    ],
    null,
    [
        'stdClass' => [
            'good' => [
                'night',
            ],
        ],
        'Symfony\\Component\\VarExporter\\Tests\\Fixtures\\GoodNight' => [
            'foo' => [
                'afternoon',
            ],
            'bar' => [
                'morning',
            ],
        ],
    ],
    $o[0],
    []
);
