<?php

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    use PhpMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->matchHost = true;
        $this->staticRoutes = array(
            '/test/baz' => array(array(array('_route' => 'baz'), null, null, null, null)),
            '/test/baz.html' => array(array(array('_route' => 'baz2'), null, null, null, null)),
            '/test/baz3/' => array(array(array('_route' => 'baz3'), null, null, null, null)),
            '/foofoo' => array(array(array('_route' => 'foofoo', 'def' => 'test'), null, null, null, null)),
            '/spa ce' => array(array(array('_route' => 'space'), null, null, null, null)),
            '/multi/new' => array(array(array('_route' => 'overridden2'), null, null, null, null)),
            '/multi/hey/' => array(array(array('_route' => 'hey'), null, null, null, null)),
            '/ababa' => array(array(array('_route' => 'ababa'), null, null, null, null)),
            '/route1' => array(array(array('_route' => 'route1'), 'a.example.com', null, null, null)),
            '/c2/route2' => array(array(array('_route' => 'route2'), 'a.example.com', null, null, null)),
            '/route4' => array(array(array('_route' => 'route4'), 'a.example.com', null, null, null)),
            '/c2/route3' => array(array(array('_route' => 'route3'), 'b.example.com', null, null, null)),
            '/route5' => array(array(array('_route' => 'route5'), 'c.example.com', null, null, null)),
            '/route6' => array(array(array('_route' => 'route6'), null, null, null, null)),
            '/route11' => array(array(array('_route' => 'route11'), '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null, null)),
            '/route12' => array(array(array('_route' => 'route12', 'var1' => 'val'), '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null, null)),
            '/route17' => array(array(array('_route' => 'route17'), null, null, null, null)),
        );
        $this->regexpList = array(
            0 => '{^(?'
                .'|(?:(?:[^./]*+\\.)++)(?'
                    .'|/foo/(baz|symfony)(*:47)'
                    .'|/bar(?'
                        .'|/([^/]++)(*:70)'
                        .'|head/([^/]++)(*:90)'
                    .')'
                    .'|/test/([^/]++)/(?'
                        .'|(*:116)'
                    .')'
                    .'|/([\']+)(*:132)'
                    .'|/a/(?'
                        .'|b\'b/([^/]++)(?'
                            .'|(*:161)'
                            .'|(*:169)'
                        .')'
                        .'|(.*)(*:182)'
                        .'|b\'b/([^/]++)(?'
                            .'|(*:205)'
                            .'|(*:213)'
                        .')'
                    .')'
                    .'|/multi/hello(?:/([^/]++))?(*:249)'
                    .'|/([^/]++)/b/([^/]++)(?'
                        .'|(*:280)'
                        .'|(*:288)'
                    .')'
                    .'|/aba/([^/]++)(*:310)'
                .')|(?i:([^\\.]++)\\.example\\.com)\\.(?'
                    .'|/route1(?'
                        .'|3/([^/]++)(*:372)'
                        .'|4/([^/]++)(*:390)'
                    .')'
                .')|(?i:c\\.example\\.com)\\.(?'
                    .'|/route15/([^/]++)(*:442)'
                .')|(?:(?:[^./]*+\\.)++)(?'
                    .'|/route16/([^/]++)(*:490)'
                    .'|/a/(?'
                        .'|a\\.\\.\\.(*:511)'
                        .'|b/(?'
                            .'|([^/]++)(*:532)'
                            .'|c/([^/]++)(*:550)'
                        .')'
                    .')'
                .')'
                .')$}sD',
        );
        $this->dynamicRoutes = array(
            47 => array(array(array('_route' => 'foo', 'def' => 'test'), array('bar'), null, null, null)),
            70 => array(array(array('_route' => 'bar'), array('foo'), array('GET' => 0, 'HEAD' => 1), null, null)),
            90 => array(array(array('_route' => 'barhead'), array('foo'), array('GET' => 0), null, null)),
            116 => array(
                array(array('_route' => 'baz4'), array('foo'), null, null, null),
                array(array('_route' => 'baz5'), array('foo'), array('POST' => 0), null, null),
                array(array('_route' => 'baz.baz6'), array('foo'), array('PUT' => 0), null, null),
            ),
            132 => array(array(array('_route' => 'quoter'), array('quoter'), null, null, null)),
            161 => array(array(array('_route' => 'foo1'), array('foo'), array('PUT' => 0), null, null)),
            169 => array(array(array('_route' => 'bar1'), array('bar'), null, null, null)),
            182 => array(array(array('_route' => 'overridden'), array('var'), null, null, null)),
            205 => array(array(array('_route' => 'foo2'), array('foo1'), null, null, null)),
            213 => array(array(array('_route' => 'bar2'), array('bar1'), null, null, null)),
            249 => array(array(array('_route' => 'helloWorld', 'who' => 'World!'), array('who'), null, null, null)),
            280 => array(array(array('_route' => 'foo3'), array('_locale', 'foo'), null, null, null)),
            288 => array(array(array('_route' => 'bar3'), array('_locale', 'bar'), null, null, null)),
            310 => array(array(array('_route' => 'foo4'), array('foo'), null, null, null)),
            372 => array(array(array('_route' => 'route13'), array('var1', 'name'), null, null, null)),
            390 => array(array(array('_route' => 'route14', 'var1' => 'val'), array('var1', 'name'), null, null, null)),
            442 => array(array(array('_route' => 'route15'), array('name'), null, null, null)),
            490 => array(array(array('_route' => 'route16', 'var1' => 'val'), array('name'), null, null, null)),
            511 => array(array(array('_route' => 'a'), array(), null, null, null)),
            532 => array(array(array('_route' => 'b'), array('var'), null, null, null)),
            550 => array(array(array('_route' => 'c'), array('var'), null, null, null)),
        );
    }
}
