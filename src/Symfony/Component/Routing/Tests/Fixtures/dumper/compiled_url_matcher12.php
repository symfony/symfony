<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/abc([^/]++)/(?'
                    .'|1(?'
                        .'|(*:27)'
                        .'|0(?'
                            .'|(*:38)'
                            .'|0(*:46)'
                        .')'
                    .')'
                    .'|2(?'
                        .'|(*:59)'
                        .'|0(?'
                            .'|(*:70)'
                            .'|0(*:78)'
                        .')'
                    .')'
                .')'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        27 => [[['_route' => 'r1'], ['foo'], null, null, false, false, null]],
        38 => [[['_route' => 'r10'], ['foo'], null, null, false, false, null]],
        46 => [[['_route' => 'r100'], ['foo'], null, null, false, false, null]],
        59 => [[['_route' => 'r2'], ['foo'], null, null, false, false, null]],
        70 => [[['_route' => 'r20'], ['foo'], null, null, false, false, null]],
        78 => [
            [['_route' => 'r200'], ['foo'], null, null, false, false, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
