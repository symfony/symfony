<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    true, // $matchHost
    [ // $staticRoutes
        '/test/baz' => [[['_route' => 'baz', '_route_path' => '/test/baz'], null, null, null, false, false, null]],
        '/test/baz.html' => [[['_route' => 'baz2', '_route_path' => '/test/baz.html'], null, null, null, false, false, null]],
        '/test/baz3' => [[['_route' => 'baz3', '_route_path' => '/test/baz3/'], null, null, null, true, false, null]],
        '/foofoo' => [[['_route' => 'foofoo', '_route_path' => '/foofoo', 'def' => 'test'], null, null, null, false, false, null]],
        '/spa ce' => [[['_route' => 'space', '_route_path' => '/spa ce'], null, null, null, false, false, null]],
        '/multi/new' => [[['_route' => 'overridden2', '_route_path' => '/multi/new'], null, null, null, false, false, null]],
        '/multi/hey' => [[['_route' => 'hey', '_route_path' => '/multi/hey/'], null, null, null, true, false, null]],
        '/ababa' => [[['_route' => 'ababa', '_route_path' => '/ababa'], null, null, null, false, false, null]],
        '/route1' => [[['_route' => 'route1', '_route_path' => '/route1'], 'a.example.com', null, null, false, false, null]],
        '/c2/route2' => [[['_route' => 'route2', '_route_path' => '/c2/route2'], 'a.example.com', null, null, false, false, null]],
        '/route4' => [[['_route' => 'route4', '_route_path' => '/route4'], 'a.example.com', null, null, false, false, null]],
        '/c2/route3' => [[['_route' => 'route3', '_route_path' => '/c2/route3'], 'b.example.com', null, null, false, false, null]],
        '/route5' => [[['_route' => 'route5', '_route_path' => '/route5'], 'c.example.com', null, null, false, false, null]],
        '/route6' => [[['_route' => 'route6', '_route_path' => '/route6'], null, null, null, false, false, null]],
        '/route11' => [[['_route' => 'route11', '_route_path' => '/route11'], '{^(?P<var1>[^\\.]++)\\.example\\.com$}sDi', null, null, false, false, null]],
        '/route12' => [[['_route' => 'route12', '_route_path' => '/route12', 'var1' => 'val'], '{^(?P<var1>[^\\.]++)\\.example\\.com$}sDi', null, null, false, false, null]],
        '/route17' => [[['_route' => 'route17', '_route_path' => '/route17'], null, null, null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
            .'|(?:(?:[^./]*+\\.)++)(?'
                .'|/foo/(baz|symfony)(*:47)'
                .'|/bar(?'
                    .'|/([^/]++)(*:70)'
                    .'|head/([^/]++)(*:90)'
                .')'
                .'|/test/([^/]++)(?'
                    .'|(*:115)'
                .')'
                .'|/([\']+)(*:131)'
                .'|/a/(?'
                    .'|b\'b/([^/]++)(?'
                        .'|(*:160)'
                        .'|(*:168)'
                    .')'
                    .'|(.*)(*:181)'
                    .'|b\'b/([^/]++)(?'
                        .'|(*:204)'
                        .'|(*:212)'
                    .')'
                .')'
                .'|/multi/hello(?:/([^/]++))?(*:248)'
                .'|/([^/]++)/b/([^/]++)(?'
                    .'|(*:279)'
                    .'|(*:287)'
                .')'
                .'|/aba/([^/]++)(*:309)'
            .')|(?i:([^\\.]++)\\.example\\.com)\\.(?'
                .'|/route1(?'
                    .'|3/([^/]++)(*:371)'
                    .'|4/([^/]++)(*:389)'
                .')'
            .')|(?i:c\\.example\\.com)\\.(?'
                .'|/route15/([^/]++)(*:441)'
            .')|(?:(?:[^./]*+\\.)++)(?'
                .'|/route16/([^/]++)(*:489)'
                .'|/a/(?'
                    .'|a\\.\\.\\.(*:510)'
                    .'|b/(?'
                        .'|([^/]++)(*:531)'
                        .'|c/([^/]++)(*:549)'
                    .')'
                .')'
            .')'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        47 => [[['_route' => 'foo', '_route_path' => '/foo/{bar}', 'def' => 'test'], ['bar'], null, null, false, true, null]],
        70 => [[['_route' => 'bar', '_route_path' => '/bar/{foo}'], ['foo'], ['GET' => 0, 'HEAD' => 1], null, false, true, null]],
        90 => [[['_route' => 'barhead', '_route_path' => '/barhead/{foo}'], ['foo'], ['GET' => 0], null, false, true, null]],
        115 => [
            [['_route' => 'baz4', '_route_path' => '/test/{foo}/'], ['foo'], null, null, true, true, null],
            [['_route' => 'baz5', '_route_path' => '/test/{foo}/'], ['foo'], ['POST' => 0], null, true, true, null],
            [['_route' => 'baz.baz6', '_route_path' => '/test/{foo}/'], ['foo'], ['PUT' => 0], null, true, true, null],
        ],
        131 => [[['_route' => 'quoter', '_route_path' => '/{quoter}'], ['quoter'], null, null, false, true, null]],
        160 => [[['_route' => 'foo1', '_route_path' => '/a/b\'b/{foo}'], ['foo'], ['PUT' => 0], null, false, true, null]],
        168 => [[['_route' => 'bar1', '_route_path' => '/a/b\'b/{bar}'], ['bar'], null, null, false, true, null]],
        181 => [[['_route' => 'overridden', '_route_path' => '/a/{var}'], ['var'], null, null, false, true, null]],
        204 => [[['_route' => 'foo2', '_route_path' => '/a/b\'b/{foo1}'], ['foo1'], null, null, false, true, null]],
        212 => [[['_route' => 'bar2', '_route_path' => '/a/b\'b/{bar1}'], ['bar1'], null, null, false, true, null]],
        248 => [[['_route' => 'helloWorld', '_route_path' => '/multi/hello/{who}', 'who' => 'World!'], ['who'], null, null, false, true, null]],
        279 => [[['_route' => 'foo3', '_route_path' => '/{_locale}/b/{foo}'], ['_locale', 'foo'], null, null, false, true, null]],
        287 => [[['_route' => 'bar3', '_route_path' => '/{_locale}/b/{bar}'], ['_locale', 'bar'], null, null, false, true, null]],
        309 => [[['_route' => 'foo4', '_route_path' => '/aba/{foo}'], ['foo'], null, null, false, true, null]],
        371 => [[['_route' => 'route13', '_route_path' => '/route13/{name}'], ['var1', 'name'], null, null, false, true, null]],
        389 => [[['_route' => 'route14', '_route_path' => '/route14/{name}', 'var1' => 'val'], ['var1', 'name'], null, null, false, true, null]],
        441 => [[['_route' => 'route15', '_route_path' => '/route15/{name}'], ['name'], null, null, false, true, null]],
        489 => [[['_route' => 'route16', '_route_path' => '/route16/{name}', 'var1' => 'val'], ['name'], null, null, false, true, null]],
        510 => [[['_route' => 'a', '_route_path' => '/a/a...'], [], null, null, false, false, null]],
        531 => [[['_route' => 'b', '_route_path' => '/a/b/{var}'], ['var'], null, null, false, true, null]],
        549 => [
            [['_route' => 'c', '_route_path' => '/a/b/c/{var}'], ['var'], null, null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
