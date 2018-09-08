<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (\Symfony\Component\VarExporter\Internal\Registry::$prototypes[\ArrayIterator::class] ?? \Symfony\Component\VarExporter\Internal\Registry::p(\ArrayIterator::class)),
    ],
    null,
    [
        \ArrayIterator::class => [
            "\0" => [
                [
                    [
                        123,
                    ],
                    1,
                ],
            ],
        ],
    ],
    $o[0],
    []
);
