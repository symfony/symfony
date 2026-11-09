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
            .'|(?=(?i:(?:[^\\.]++)\\.exampple\\.com)\\.)(?=[^/]*+(.*+))(?i:([^\\.]++)\\.exampple\\.com)\\.(?=\\1)(?'
                .'|/abc([^/]++)(?'
                    .'|(*:114)'
                .')'
            .')'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        114 => [
            [['_route' => 'r1'], ['foo', 'foo', '_route'], null, null, false, true, null],
            [['_route' => 'r2'], ['foo', 'foo', '_route'], null, null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
