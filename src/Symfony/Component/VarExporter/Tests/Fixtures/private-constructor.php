<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\PrivateConstructor'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\PrivateConstructor')),
    ],
    null,
    [
        'stdClass' => [
            'prop' => [
                'bar',
            ],
        ],
    ],
    $o[0],
    []
);
