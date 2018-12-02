<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes['Symfony\\Component\\VarExporter\\Tests\\ConcreteClass'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\ConcreteClass')),
    ],
    null,
    [
        'Symfony\\Component\\VarExporter\\Tests\\ConcreteClass' => [
            'foo' => [
                123,
            ],
        ],
    ],
    $o[0],
    []
);
