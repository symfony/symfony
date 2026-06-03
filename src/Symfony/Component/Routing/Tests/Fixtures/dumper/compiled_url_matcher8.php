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
                .'|/(a)(*:11)'
            .')/?$}sD',
        11 => '{^(?'
                .'|/(.)(*:22)'
            .')/?$}sDu',
        22 => '{^(?'
                .'|/(.)(*:33)'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        11 => [[['_route' => 'a'], ['a'], null, null, false, true, null]],
        22 => [[['_route' => 'b'], ['a'], null, null, false, true, null]],
        33 => [
            [['_route' => 'c'], ['a'], null, null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
