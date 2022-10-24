<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/trailing/simple/no-methods' => [[['_route' => 'simple_trailing_slash_no_methods', '_route_path' => '/trailing/simple/no-methods/'], null, null, null, true, false, null]],
        '/trailing/simple/get-method' => [[['_route' => 'simple_trailing_slash_GET_method', '_route_path' => '/trailing/simple/get-method/'], null, ['GET' => 0], null, true, false, null]],
        '/trailing/simple/head-method' => [[['_route' => 'simple_trailing_slash_HEAD_method', '_route_path' => '/trailing/simple/head-method/'], null, ['HEAD' => 0], null, true, false, null]],
        '/trailing/simple/post-method' => [[['_route' => 'simple_trailing_slash_POST_method', '_route_path' => '/trailing/simple/post-method/'], null, ['POST' => 0], null, true, false, null]],
        '/not-trailing/simple/no-methods' => [[['_route' => 'simple_not_trailing_slash_no_methods', '_route_path' => '/not-trailing/simple/no-methods'], null, null, null, false, false, null]],
        '/not-trailing/simple/get-method' => [[['_route' => 'simple_not_trailing_slash_GET_method', '_route_path' => '/not-trailing/simple/get-method'], null, ['GET' => 0], null, false, false, null]],
        '/not-trailing/simple/head-method' => [[['_route' => 'simple_not_trailing_slash_HEAD_method', '_route_path' => '/not-trailing/simple/head-method'], null, ['HEAD' => 0], null, false, false, null]],
        '/not-trailing/simple/post-method' => [[['_route' => 'simple_not_trailing_slash_POST_method', '_route_path' => '/not-trailing/simple/post-method'], null, ['POST' => 0], null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/trailing/regex/(?'
                    .'|no\\-methods/([^/]++)(*:46)'
                    .'|get\\-method/([^/]++)(*:73)'
                    .'|head\\-method/([^/]++)(*:101)'
                    .'|post\\-method/([^/]++)(*:130)'
                .')'
                .'|/not\\-trailing/regex/(?'
                    .'|no\\-methods/([^/]++)(*:183)'
                    .'|get\\-method/([^/]++)(*:211)'
                    .'|head\\-method/([^/]++)(*:240)'
                    .'|post\\-method/([^/]++)(*:269)'
                .')'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        46 => [[['_route' => 'regex_trailing_slash_no_methods', '_route_path' => '/trailing/regex/no-methods/{param}/'], ['param'], null, null, true, true, null]],
        73 => [[['_route' => 'regex_trailing_slash_GET_method', '_route_path' => '/trailing/regex/get-method/{param}/'], ['param'], ['GET' => 0], null, true, true, null]],
        101 => [[['_route' => 'regex_trailing_slash_HEAD_method', '_route_path' => '/trailing/regex/head-method/{param}/'], ['param'], ['HEAD' => 0], null, true, true, null]],
        130 => [[['_route' => 'regex_trailing_slash_POST_method', '_route_path' => '/trailing/regex/post-method/{param}/'], ['param'], ['POST' => 0], null, true, true, null]],
        183 => [[['_route' => 'regex_not_trailing_slash_no_methods', '_route_path' => '/not-trailing/regex/no-methods/{param}'], ['param'], null, null, false, true, null]],
        211 => [[['_route' => 'regex_not_trailing_slash_GET_method', '_route_path' => '/not-trailing/regex/get-method/{param}'], ['param'], ['GET' => 0], null, false, true, null]],
        240 => [[['_route' => 'regex_not_trailing_slash_HEAD_method', '_route_path' => '/not-trailing/regex/head-method/{param}'], ['param'], ['HEAD' => 0], null, false, true, null]],
        269 => [
            [['_route' => 'regex_not_trailing_slash_POST_method', '_route_path' => '/not-trailing/regex/post-method/{param}'], ['param'], ['POST' => 0], null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
