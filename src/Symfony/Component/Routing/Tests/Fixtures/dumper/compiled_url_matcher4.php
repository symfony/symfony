<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/just_head' => [[['_route' => 'just_head'], null, ['HEAD' => 0], null, false, false, null, null]],
        '/head_and_get' => [[['_route' => 'head_and_get'], null, ['HEAD' => 0, 'GET' => 1], null, false, false, null, null]],
        '/get_and_head' => [[['_route' => 'get_and_head'], null, ['GET' => 0, 'HEAD' => 1], null, false, false, null, null]],
        '/post_and_head' => [[['_route' => 'post_and_head'], null, ['POST' => 0, 'HEAD' => 1], null, false, false, null, null]],
        '/put_and_post' => [
            [['_route' => 'put_and_post'], null, ['PUT' => 0, 'POST' => 1], null, false, false, null, null],
            [['_route' => 'put_and_get_and_head'], null, ['PUT' => 0, 'GET' => 1, 'HEAD' => 2], null, false, false, null, null],
        ],
    ],
    [ // $regexpList
    ],
    [ // $dynamicRoutes
    ],
    null, // $checkCondition
];
