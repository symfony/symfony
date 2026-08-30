<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/users' => [
            [['_route' => 'api'], null, null, null, false, false, null, 8001],
            [['_route' => 'admin'], null, null, null, false, false, null, 8002],
            [['_route' => 'front'], null, null, null, false, false, null, null],
        ],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/users/([^/]++)(*:22)'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        22 => [
            [['_route' => 'api_dynamic'], ['id'], null, null, false, true, null, 8001],
            [null, null, null, null, false, false, 0, null],
        ],
    ],
    null, // $checkCondition
];
