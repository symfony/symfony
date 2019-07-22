<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['Symfony\\Component\\VarExporter\\Tests\\Php74Serializable'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('Symfony\\Component\\VarExporter\\Tests\\Php74Serializable')),
        clone ($p['stdClass'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('stdClass')),
    ],
    null,
    [],
    $o[0],
    [
        [
            $o[1],
        ],
    ]
);
