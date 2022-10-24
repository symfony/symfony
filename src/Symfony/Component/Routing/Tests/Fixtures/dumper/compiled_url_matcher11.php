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
                .'|/(en|fr)/(?'
                    .'|admin/post(?'
                        .'|(*:32)'
                        .'|/(?'
                            .'|new(*:46)'
                            .'|(\\d+)(*:58)'
                            .'|(\\d+)/edit(*:75)'
                            .'|(\\d+)/delete(*:94)'
                        .')'
                    .')'
                    .'|blog(?'
                        .'|(*:110)'
                        .'|/(?'
                            .'|rss\\.xml(*:130)'
                            .'|p(?'
                                .'|age/([^/]++)(*:154)'
                                .'|osts/([^/]++)(*:175)'
                            .')'
                            .'|comments/(\\d+)/new(*:202)'
                            .'|search(*:216)'
                        .')'
                    .')'
                    .'|log(?'
                        .'|in(*:234)'
                        .'|out(*:245)'
                    .')'
                .')'
                .'|/(en|fr)?(*:264)'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        32 => [[['_route' => 'a', '_route_path' => '/{_locale}/admin/post/', '_locale' => 'en'], ['_locale'], null, null, true, false, null]],
        46 => [[['_route' => 'b', '_route_path' => '/{_locale}/admin/post/new', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
        58 => [[['_route' => 'c', '_route_path' => '/{_locale}/admin/post/{id}', '_locale' => 'en'], ['_locale', 'id'], null, null, false, true, null]],
        75 => [[['_route' => 'd', '_route_path' => '/{_locale}/admin/post/{id}/edit', '_locale' => 'en'], ['_locale', 'id'], null, null, false, false, null]],
        94 => [[['_route' => 'e', '_route_path' => '/{_locale}/admin/post/{id}/delete', '_locale' => 'en'], ['_locale', 'id'], null, null, false, false, null]],
        110 => [[['_route' => 'f', '_route_path' => '/{_locale}/blog/', '_locale' => 'en'], ['_locale'], null, null, true, false, null]],
        130 => [[['_route' => 'g', '_route_path' => '/{_locale}/blog/rss.xml', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
        154 => [[['_route' => 'h', '_route_path' => '/{_locale}/blog/page/{page}', '_locale' => 'en'], ['_locale', 'page'], null, null, false, true, null]],
        175 => [[['_route' => 'i', '_route_path' => '/{_locale}/blog/posts/{page}', '_locale' => 'en'], ['_locale', 'page'], null, null, false, true, null]],
        202 => [[['_route' => 'j', '_route_path' => '/{_locale}/blog/comments/{id}/new', '_locale' => 'en'], ['_locale', 'id'], null, null, false, false, null]],
        216 => [[['_route' => 'k', '_route_path' => '/{_locale}/blog/search', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
        234 => [[['_route' => 'l', '_route_path' => '/{_locale}/login', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
        245 => [[['_route' => 'm', '_route_path' => '/{_locale}/logout', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
        264 => [
            [['_route' => 'n', '_route_path' => '/{_locale}', '_locale' => 'en'], ['_locale'], null, null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
