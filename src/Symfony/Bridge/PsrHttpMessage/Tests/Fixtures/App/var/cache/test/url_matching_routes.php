<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/server-request' => [[['_route' => 'server_request', '_controller' => ['Symfony\\Bridge\\PsrHttpMessage\\Tests\\Fixtures\\App\\Controller\\PsrRequestController', 'serverRequestAction']], null, ['GET' => 0], null, false, false, null]],
        '/request' => [[['_route' => 'request', '_controller' => ['Symfony\\Bridge\\PsrHttpMessage\\Tests\\Fixtures\\App\\Controller\\PsrRequestController', 'requestAction']], null, ['POST' => 0], null, false, false, null]],
        '/message' => [[['_route' => 'message', '_controller' => ['Symfony\\Bridge\\PsrHttpMessage\\Tests\\Fixtures\\App\\Controller\\PsrRequestController', 'messageAction']], null, ['PUT' => 0], null, false, false, null]],
    ],
    [ // $regexpList
    ],
    [ // $dynamicRoutes
    ],
    null, // $checkCondition
];
