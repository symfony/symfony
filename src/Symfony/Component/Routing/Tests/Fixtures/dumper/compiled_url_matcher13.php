<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    true, // $matchHost
    [ // $staticRoutes
    ],
    [ // $regexpList
        0 => '{^(?'
            .'|(?i:([^\\.]++)\\.example\\.com)\\.(?'
                .'|/abc([^/]++)(?'
                    .'|(*:55)'
                .')'
            .')'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        55 => [
            [['_route' => 'r1'], ['foo', 'foo'], null, null, false, true, null],
            [['_route' => 'r2'], ['foo', 'foo'], null, null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
