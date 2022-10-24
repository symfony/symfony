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
        27 => [[['_route' => 'r1', '_route_path' => '/abc{foo}/1'], ['foo'], null, null, false, false, null]],
        38 => [[['_route' => 'r10', '_route_path' => '/abc{foo}/10'], ['foo'], null, null, false, false, null]],
        46 => [[['_route' => 'r100', '_route_path' => '/abc{foo}/100'], ['foo'], null, null, false, false, null]],
        59 => [[['_route' => 'r2', '_route_path' => '/abc{foo}/2'], ['foo'], null, null, false, false, null]],
        70 => [[['_route' => 'r20', '_route_path' => '/abc{foo}/20'], ['foo'], null, null, false, false, null]],
        78 => [
            [['_route' => 'r200', '_route_path' => '/abc{foo}/200'], ['foo'], null, null, false, false, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
