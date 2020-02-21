<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/a' => [[['_route' => 'a'], null, null, null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/(.)(*:11)'
            .')/?$}sDu',
        11 => '{^(?'
                .'|/(.)(*:22)'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        11 => [[['_route' => 'b'], ['a'], null, null, false, true, null]],
        22 => [
            [['_route' => 'c'], ['a'], null, null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
