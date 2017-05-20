<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * ProjectUrlMatcher.
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    /**
     * Constructor.
     */
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($pathinfo)
    {
        $allow = array();
        $pathinfo = rawurldecode($pathinfo);
        $trimmedPathinfo = rtrim($pathinfo, '/');
        $context = $this->context;
        $request = $this->request;
        $requestMethod = $canonicalMethod = $context->getMethod();
        $scheme = $context->getScheme();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }


        if (0 === strpos($pathinfo, '/foo')) {
            // foo
            if (preg_match('#^/foo/(?P<bar>baz|symfony)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo')), array (  'def' => 'test',));
            }

            // foofoo
            if ('/foofoo' === $pathinfo) {
                return array (  'def' => 'test',  '_route' => 'foofoo',);
            }

        }

        elseif (0 === strpos($pathinfo, '/bar')) {
            // bar
            if (preg_match('#^/bar/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_bar;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'bar')), array ());
            }
            not_bar:

            // barhead
            if (0 === strpos($pathinfo, '/barhead') && preg_match('#^/barhead/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_barhead;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'barhead')), array ());
            }
            not_barhead:

        }

        elseif (0 === strpos($pathinfo, '/test')) {
            if (0 === strpos($pathinfo, '/test/baz')) {
                // baz
                if ('/test/baz' === $pathinfo) {
                    return array('_route' => 'baz');
                }

                // baz2
                if ('/test/baz.html' === $pathinfo) {
                    return array('_route' => 'baz2');
                }

                // baz3
                if ('/test/baz3/' === $pathinfo) {
                    return array('_route' => 'baz3');
                }

            }

            // baz4
            if (preg_match('#^/test/(?P<foo>[^/]++)/$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'baz4')), array ());
            }

            // baz5
            if (preg_match('#^/test/(?P<foo>[^/]++)/$#s', $pathinfo, $matches)) {
                if ('POST' !== $canonicalMethod) {
                    $allow[] = 'POST';
                    goto not_baz5;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'baz5')), array ());
            }
            not_baz5:

            // baz.baz6
            if (preg_match('#^/test/(?P<foo>[^/]++)/$#s', $pathinfo, $matches)) {
                if ('PUT' !== $canonicalMethod) {
                    $allow[] = 'PUT';
                    goto not_bazbaz6;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'baz.baz6')), array ());
            }
            not_bazbaz6:

        }

        // quoter
        if (preg_match('#^/(?P<quoter>[\']+)$#s', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'quoter')), array ());
        }

        // space
        if ('/spa ce' === $pathinfo) {
            return array('_route' => 'space');
        }

        if (0 === strpos($pathinfo, '/a')) {
            if (0 === strpos($pathinfo, '/a/b\'b')) {
                // foo1
                if (preg_match('#^/a/b\'b/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo1')), array ());
                }

                // bar1
                if (preg_match('#^/a/b\'b/(?P<bar>[^/]++)$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => 'bar1')), array ());
                }

            }

            // overridden
            if (preg_match('#^/a/(?P<var>.*)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'overridden')), array ());
            }

            if (0 === strpos($pathinfo, '/a/b\'b')) {
                // foo2
                if (preg_match('#^/a/b\'b/(?P<foo1>[^/]++)$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo2')), array ());
                }

                // bar2
                if (preg_match('#^/a/b\'b/(?P<bar1>[^/]++)$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => 'bar2')), array ());
                }

            }

        }

        elseif (0 === strpos($pathinfo, '/multi')) {
            // helloWorld
            if (0 === strpos($pathinfo, '/multi/hello') && preg_match('#^/multi/hello(?:/(?P<who>[^/]++))?$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'helloWorld')), array (  'who' => 'World!',));
            }

            // hey
            if ('/multi/hey/' === $pathinfo) {
                return array('_route' => 'hey');
            }

            // overridden2
            if ('/multi/new' === $pathinfo) {
                return array('_route' => 'overridden2');
            }

        }

        // foo3
        if (preg_match('#^/(?P<_locale>[^/]++)/b/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo3')), array ());
        }

        // bar3
        if (preg_match('#^/(?P<_locale>[^/]++)/b/(?P<bar>[^/]++)$#s', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'bar3')), array ());
        }

        if (0 === strpos($pathinfo, '/aba')) {
            // ababa
            if ('/ababa' === $pathinfo) {
                return array('_route' => 'ababa');
            }

            // foo4
            if (preg_match('#^/aba/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo4')), array ());
            }

        }

        $host = $context->getHost();

        if (preg_match('#^a\\.example\\.com$#si', $host, $hostMatches)) {
            // route1
            if ('/route1' === $pathinfo) {
                return array('_route' => 'route1');
            }

            // route2
            if ('/c2/route2' === $pathinfo) {
                return array('_route' => 'route2');
            }

        }

        if (preg_match('#^b\\.example\\.com$#si', $host, $hostMatches)) {
            // route3
            if ('/c2/route3' === $pathinfo) {
                return array('_route' => 'route3');
            }

        }

        if (preg_match('#^a\\.example\\.com$#si', $host, $hostMatches)) {
            // route4
            if ('/route4' === $pathinfo) {
                return array('_route' => 'route4');
            }

        }

        if (preg_match('#^c\\.example\\.com$#si', $host, $hostMatches)) {
            // route5
            if ('/route5' === $pathinfo) {
                return array('_route' => 'route5');
            }

        }

        // route6
        if ('/route6' === $pathinfo) {
            return array('_route' => 'route6');
        }

        if (preg_match('#^(?P<var1>[^\\.]++)\\.example\\.com$#si', $host, $hostMatches)) {
            if (0 === strpos($pathinfo, '/route1')) {
                // route11
                if ('/route11' === $pathinfo) {
                    return $this->mergeDefaults(array_replace($hostMatches, array('_route' => 'route11')), array ());
                }

                // route12
                if ('/route12' === $pathinfo) {
                    return $this->mergeDefaults(array_replace($hostMatches, array('_route' => 'route12')), array (  'var1' => 'val',));
                }

                // route13
                if (0 === strpos($pathinfo, '/route13') && preg_match('#^/route13/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($hostMatches, $matches, array('_route' => 'route13')), array ());
                }

                // route14
                if (0 === strpos($pathinfo, '/route14') && preg_match('#^/route14/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($hostMatches, $matches, array('_route' => 'route14')), array (  'var1' => 'val',));
                }

            }

        }

        if (preg_match('#^c\\.example\\.com$#si', $host, $hostMatches)) {
            // route15
            if (0 === strpos($pathinfo, '/route15') && preg_match('#^/route15/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'route15')), array ());
            }

        }

        // route16
        if (0 === strpos($pathinfo, '/route16') && preg_match('#^/route16/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'route16')), array (  'var1' => 'val',));
        }

        // route17
        if ('/route17' === $pathinfo) {
            return array('_route' => 'route17');
        }

        // a
        if ('/a/a...' === $pathinfo) {
            return array('_route' => 'a');
        }

        if (0 === strpos($pathinfo, '/a/b')) {
            // b
            if (preg_match('#^/a/b/(?P<var>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'b')), array ());
            }

            // c
            if (0 === strpos($pathinfo, '/a/b/c') && preg_match('#^/a/b/c/(?P<var>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'c')), array ());
            }

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
