<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/a/11' => [[['_route' => 'a_first'], null, null, null, false, false, null, null]],
        '/a/22' => [[['_route' => 'a_second'], null, null, null, false, false, null, null]],
        '/a/333' => [[['_route' => 'a_third'], null, null, null, false, false, null, null]],
        '/a/44' => [[['_route' => 'a_fourth'], null, null, null, true, false, null, null]],
        '/a/55' => [[['_route' => 'a_fifth'], null, null, null, true, false, null, null]],
        '/a/66' => [[['_route' => 'a_sixth'], null, null, null, true, false, null, null]],
        '/nested/group/a' => [[['_route' => 'nested_a'], null, null, null, true, false, null, null]],
        '/nested/group/b' => [[['_route' => 'nested_b'], null, null, null, true, false, null, null]],
        '/nested/group/c' => [[['_route' => 'nested_c'], null, null, null, true, false, null, null]],
        '/slashed/group' => [[['_route' => 'slashed_a'], null, null, null, true, false, null, null]],
        '/slashed/group/b' => [[['_route' => 'slashed_b'], null, null, null, true, false, null, null]],
        '/slashed/group/c' => [[['_route' => 'slashed_c'], null, null, null, true, false, null, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/([^/]++)(*:16)'
                .'|/nested/([^/]++)(*:39)'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        16 => [[['_route' => 'a_wildcard'], ['param'], null, null, false, true, null, null]],
        39 => [
            [['_route' => 'nested_wildcard'], ['param'], null, null, false, true, null, null],
            [null, null, null, null, false, false, 0, null],
        ],
    ],
    null, // $checkCondition
];
