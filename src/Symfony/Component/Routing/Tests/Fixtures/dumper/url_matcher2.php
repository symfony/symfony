<?php

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Tests\Fixtures\RedirectableUrlMatcher
{
    use PhpMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->matchHost = true;
        $this->staticRoutes = array(
            '/test/baz' => array(array(array('_route' => 'baz'), null, null, null, false, false, null)),
            '/test/baz.html' => array(array(array('_route' => 'baz2'), null, null, null, false, false, null)),
            '/test/baz3' => array(array(array('_route' => 'baz3'), null, null, null, true, false, null)),
            '/foofoo' => array(array(array('_route' => 'foofoo', 'def' => 'test'), null, null, null, false, false, null)),
            '/spa ce' => array(array(array('_route' => 'space'), null, null, null, false, false, null)),
            '/multi/new' => array(array(array('_route' => 'overridden2'), null, null, null, false, false, null)),
            '/multi/hey' => array(array(array('_route' => 'hey'), null, null, null, true, false, null)),
            '/ababa' => array(array(array('_route' => 'ababa'), null, null, null, false, false, null)),
            '/route1' => array(array(array('_route' => 'route1'), 'a.example.com', null, null, false, false, null)),
            '/c2/route2' => array(array(array('_route' => 'route2'), 'a.example.com', null, null, false, false, null)),
            '/route4' => array(array(array('_route' => 'route4'), 'a.example.com', null, null, false, false, null)),
            '/c2/route3' => array(array(array('_route' => 'route3'), 'b.example.com', null, null, false, false, null)),
            '/route5' => array(array(array('_route' => 'route5'), 'c.example.com', null, null, false, false, null)),
            '/route6' => array(array(array('_route' => 'route6'), null, null, null, false, false, null)),
            '/route11' => array(array(array('_route' => 'route11'), '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null, false, false, null)),
            '/route12' => array(array(array('_route' => 'route12', 'var1' => 'val'), '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null, false, false, null)),
            '/route17' => array(array(array('_route' => 'route17'), null, null, null, false, false, null)),
            '/secure' => array(array(array('_route' => 'secure'), null, null, array('https' => 0), false, false, null)),
            '/nonsecure' => array(array(array('_route' => 'nonsecure'), null, null, array('http' => 0), false, false, null)),
        );
        $this->regexpList = array(
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
        );
        $this->dynamicRoutes = array(
            47 => array(array(array('_route' => 'foo', 'def' => 'test'), array('bar'), null, null, false, true, null)),
            70 => array(array(array('_route' => 'bar'), array('foo'), array('GET' => 0, 'HEAD' => 1), null, false, true, null)),
            90 => array(array(array('_route' => 'barhead'), array('foo'), array('GET' => 0), null, false, true, null)),
            115 => array(
                array(array('_route' => 'baz4'), array('foo'), null, null, true, true, null),
                array(array('_route' => 'baz5'), array('foo'), array('POST' => 0), null, true, true, null),
                array(array('_route' => 'baz.baz6'), array('foo'), array('PUT' => 0), null, true, true, null),
            ),
            131 => array(array(array('_route' => 'quoter'), array('quoter'), null, null, false, true, null)),
            160 => array(array(array('_route' => 'foo1'), array('foo'), array('PUT' => 0), null, false, true, null)),
            168 => array(array(array('_route' => 'bar1'), array('bar'), null, null, false, true, null)),
            181 => array(array(array('_route' => 'overridden'), array('var'), null, null, false, true, null)),
            204 => array(array(array('_route' => 'foo2'), array('foo1'), null, null, false, true, null)),
            212 => array(array(array('_route' => 'bar2'), array('bar1'), null, null, false, true, null)),
            248 => array(array(array('_route' => 'helloWorld', 'who' => 'World!'), array('who'), null, null, false, true, null)),
            279 => array(array(array('_route' => 'foo3'), array('_locale', 'foo'), null, null, false, true, null)),
            287 => array(array(array('_route' => 'bar3'), array('_locale', 'bar'), null, null, false, true, null)),
            309 => array(array(array('_route' => 'foo4'), array('foo'), null, null, false, true, null)),
            371 => array(array(array('_route' => 'route13'), array('var1', 'name'), null, null, false, true, null)),
            389 => array(array(array('_route' => 'route14', 'var1' => 'val'), array('var1', 'name'), null, null, false, true, null)),
            441 => array(array(array('_route' => 'route15'), array('name'), null, null, false, true, null)),
            489 => array(array(array('_route' => 'route16', 'var1' => 'val'), array('name'), null, null, false, true, null)),
            510 => array(array(array('_route' => 'a'), array(), null, null, false, false, null)),
            531 => array(array(array('_route' => 'b'), array('var'), null, null, false, true, null)),
            549 => array(array(array('_route' => 'c'), array('var'), null, null, false, true, null)),
        );
    }
}
