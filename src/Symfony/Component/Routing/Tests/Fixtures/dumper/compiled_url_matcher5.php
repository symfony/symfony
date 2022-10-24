<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/a/11' => [[['_route' => 'a_first', '_route_path' => '/a/11'], null, null, null, false, false, null]],
        '/a/22' => [[['_route' => 'a_second', '_route_path' => '/a/22'], null, null, null, false, false, null]],
        '/a/333' => [[['_route' => 'a_third', '_route_path' => '/a/333'], null, null, null, false, false, null]],
        '/a/44' => [[['_route' => 'a_fourth', '_route_path' => '/a/44/'], null, null, null, true, false, null]],
        '/a/55' => [[['_route' => 'a_fifth', '_route_path' => '/a/55/'], null, null, null, true, false, null]],
        '/a/66' => [[['_route' => 'a_sixth', '_route_path' => '/a/66/'], null, null, null, true, false, null]],
        '/nested/group/a' => [[['_route' => 'nested_a', '_route_path' => '/nested/group/a/'], null, null, null, true, false, null]],
        '/nested/group/b' => [[['_route' => 'nested_b', '_route_path' => '/nested/group/b/'], null, null, null, true, false, null]],
        '/nested/group/c' => [[['_route' => 'nested_c', '_route_path' => '/nested/group/c/'], null, null, null, true, false, null]],
        '/slashed/group' => [[['_route' => 'slashed_a', '_route_path' => '/slashed/group/'], null, null, null, true, false, null]],
        '/slashed/group/b' => [[['_route' => 'slashed_b', '_route_path' => '/slashed/group/b/'], null, null, null, true, false, null]],
        '/slashed/group/c' => [[['_route' => 'slashed_c', '_route_path' => '/slashed/group/c/'], null, null, null, true, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/([^/]++)(*:16)'
                .'|/nested/([^/]++)(*:39)'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        16 => [[['_route' => 'a_wildcard', '_route_path' => '/{param}'], ['param'], null, null, false, true, null]],
        39 => [
            [['_route' => 'nested_wildcard', '_route_path' => '/nested/{param}'], ['param'], null, null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
