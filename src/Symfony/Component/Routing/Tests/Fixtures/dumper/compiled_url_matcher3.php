<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/rootprefix/test' => [[['_route' => 'static'], null, null, null, false, false, null]],
        '/with-condition' => [[['_route' => 'with-condition'], null, null, null, false, false, -1]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/rootprefix/([^/]++)(*:27)'
                .'|/with\\-condition/(\\d+)(*:56)'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        27 => [[['_route' => 'dynamic'], ['var'], null, null, false, true, null]],
        56 => [
            [['_route' => 'with-condition-dynamic'], ['id'], null, null, false, true, -2],
            [null, null, null, null, false, false, 0],
        ],
    ],
    static function ($condition, $context, $request, $params) { // $checkCondition
        switch ($condition) {
            case -1: return ($context->getMethod() == "GET");
            case -2: return ($params["id"] < 100);
        }
    },
];
