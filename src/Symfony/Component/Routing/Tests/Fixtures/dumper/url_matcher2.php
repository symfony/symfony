<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Tests\Fixtures\RedirectableUrlMatcher
{
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($rawPathinfo)
    {
        $allow = array();
        $pathinfo = rawurldecode($rawPathinfo);
        $trimmedPathinfo = rtrim($pathinfo, '/');
        $context = $this->context;
        $request = $this->request ?: $this->createRequest($pathinfo);
        $requestMethod = $canonicalMethod = $context->getMethod();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($trimmedPathinfo) {
            case '/test/baz':
                // baz
                return array('_route' => 'baz');
                break;
            case '/test/baz.html':
                // baz2
                return array('_route' => 'baz2');
                break;
            case '/test/baz3':
                // baz3
                $ret = array('_route' => 'baz3');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_baz3;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'baz3'));
                }

                return $ret;
                not_baz3:
                break;
            case '/foofoo':
                // foofoo
                return array('def' => 'test', '_route' => 'foofoo');
                break;
            case '/spa ce':
                // space
                return array('_route' => 'space');
                break;
            case '/multi/new':
                // overridden2
                return array('_route' => 'overridden2');
                break;
            case '/multi/hey':
                // hey
                $ret = array('_route' => 'hey');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_hey;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'hey'));
                }

                return $ret;
                not_hey:
                break;
            case '/ababa':
                // ababa
                return array('_route' => 'ababa');
                break;
        }

        // foo
        if (0 === strpos($pathinfo, '/foo') && preg_match('#^/foo/(?P<bar>baz|symfony)$#sD', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo')), array('def' => 'test'));
        }

        if (0 === strpos($pathinfo, '/bar')) {
            // bar
            if (preg_match('#^/bar/(?P<foo>[^/]++)$#sD', $pathinfo, $matches)) {
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_bar;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'bar')), array());
            }
            not_bar:

            // barhead
            if (0 === strpos($pathinfo, '/barhead') && preg_match('#^/barhead/(?P<foo>[^/]++)$#sD', $pathinfo, $matches)) {
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_barhead;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'barhead')), array());
            }
            not_barhead:

        }

        elseif (0 === strpos($pathinfo, '/test')) {
            // baz4
            if (preg_match('#^/test/(?P<foo>[^/]++)/?$#sD', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'baz4')), array());
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_baz4;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'baz4'));
                }

                return $ret;
            }
            not_baz4:

            // baz5
            if (preg_match('#^/test/(?P<foo>[^/]++)/$#sD', $pathinfo, $matches)) {
                if ('POST' !== $canonicalMethod) {
                    $allow[] = 'POST';
                    goto not_baz5;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'baz5')), array());
            }
            not_baz5:

            // baz.baz6
            if (preg_match('#^/test/(?P<foo>[^/]++)/$#sD', $pathinfo, $matches)) {
                if ('PUT' !== $canonicalMethod) {
                    $allow[] = 'PUT';
                    goto not_bazbaz6;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'baz.baz6')), array());
            }
            not_bazbaz6:

        }

        // quoter
        if (preg_match('#^/(?P<quoter>[\']+)$#sD', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'quoter')), array());
        }

        if (0 === strpos($pathinfo, '/a')) {
            if (0 === strpos($pathinfo, '/a/b\'b')) {
                // foo1
                if (preg_match('#^/a/b\'b/(?P<foo>[^/]++)$#sD', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo1')), array());
                }

                // bar1
                if (preg_match('#^/a/b\'b/(?P<bar>[^/]++)$#sD', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => 'bar1')), array());
                }

            }

            // overridden
            if (preg_match('#^/a/(?P<var>.*)$#sD', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'overridden')), array());
            }

            if (0 === strpos($pathinfo, '/a/b\'b')) {
                // foo2
                if (preg_match('#^/a/b\'b/(?P<foo1>[^/]++)$#sD', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo2')), array());
                }

                // bar2
                if (preg_match('#^/a/b\'b/(?P<bar1>[^/]++)$#sD', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => 'bar2')), array());
                }

            }

        }

        // helloWorld
        if (0 === strpos($pathinfo, '/multi/hello') && preg_match('#^/multi/hello(?:/(?P<who>[^/]++))?$#sD', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'helloWorld')), array('who' => 'World!'));
        }

        // foo3
        if (preg_match('#^/(?P<_locale>[^/]++)/b/(?P<foo>[^/]++)$#sD', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo3')), array());
        }

        // bar3
        if (preg_match('#^/(?P<_locale>[^/]++)/b/(?P<bar>[^/]++)$#sD', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'bar3')), array());
        }

        // foo4
        if (0 === strpos($pathinfo, '/aba') && preg_match('#^/aba/(?P<foo>[^/]++)$#sD', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo4')), array());
        }

        $host = $context->getHost();

        if (preg_match('#^a\\.example\\.com$#sDi', $host, $hostMatches)) {
            switch ($trimmedPathinfo) {
                case '/route1':
                    // route1
                    return array('_route' => 'route1');
                    break;
                case '/c2/route2':
                    // route2
                    return array('_route' => 'route2');
                    break;
            }

        }

        if (preg_match('#^b\\.example\\.com$#sDi', $host, $hostMatches)) {
            switch ($trimmedPathinfo) {
                case '/c2/route3':
                    // route3
                    return array('_route' => 'route3');
                    break;
            }

        }

        if (preg_match('#^a\\.example\\.com$#sDi', $host, $hostMatches)) {
            switch ($trimmedPathinfo) {
                case '/route4':
                    // route4
                    return array('_route' => 'route4');
                    break;
            }

        }

        if (preg_match('#^c\\.example\\.com$#sDi', $host, $hostMatches)) {
            switch ($trimmedPathinfo) {
                case '/route5':
                    // route5
                    return array('_route' => 'route5');
                    break;
            }

        }

        switch ($trimmedPathinfo) {
            case '/route6':
                // route6
                return array('_route' => 'route6');
                break;
        }

        if (preg_match('#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', $host, $hostMatches)) {
            switch ($trimmedPathinfo) {
                case '/route11':
                    // route11
                    return $this->mergeDefaults(array_replace($hostMatches, array('_route' => 'route11')), array());
                    break;
                case '/route12':
                    // route12
                    return $this->mergeDefaults(array_replace($hostMatches, array('_route' => 'route12')), array('var1' => 'val'));
                    break;
            }

            // route13
            if (0 === strpos($pathinfo, '/route13') && preg_match('#^/route13/(?P<name>[^/]++)$#sD', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($hostMatches, $matches, array('_route' => 'route13')), array());
            }

            // route14
            if (0 === strpos($pathinfo, '/route14') && preg_match('#^/route14/(?P<name>[^/]++)$#sD', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($hostMatches, $matches, array('_route' => 'route14')), array('var1' => 'val'));
            }

        }

        if (preg_match('#^c\\.example\\.com$#sDi', $host, $hostMatches)) {
            // route15
            if (0 === strpos($pathinfo, '/route15') && preg_match('#^/route15/(?P<name>[^/]++)$#sD', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'route15')), array());
            }

        }

        switch ($trimmedPathinfo) {
            case '/route17':
                // route17
                return array('_route' => 'route17');
                break;
            case '/a/a...':
                // a
                return array('_route' => 'a');
                break;
            case '/secure':
                // secure
                $ret = array('_route' => 'secure');
                $requiredSchemes = array('https' => 0);
                if (!isset($requiredSchemes[$context->getScheme()])) {
                    return array_replace($ret, $this->redirect($rawPathinfo, 'secure', key($requiredSchemes)));
                }

                return $ret;
                break;
            case '/nonsecure':
                // nonsecure
                $ret = array('_route' => 'nonsecure');
                $requiredSchemes = array('http' => 0);
                if (!isset($requiredSchemes[$context->getScheme()])) {
                    return array_replace($ret, $this->redirect($rawPathinfo, 'nonsecure', key($requiredSchemes)));
                }

                return $ret;
                break;
        }

        // route16
        if (0 === strpos($pathinfo, '/route16') && preg_match('#^/route16/(?P<name>[^/]++)$#sD', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'route16')), array('var1' => 'val'));
        }

        if (0 === strpos($pathinfo, '/a/b')) {
            // b
            if (preg_match('#^/a/b/(?P<var>[^/]++)$#sD', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'b')), array());
            }

            // c
            if (0 === strpos($pathinfo, '/a/b/c') && preg_match('#^/a/b/c/(?P<var>[^/]++)$#sD', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'c')), array());
            }

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
