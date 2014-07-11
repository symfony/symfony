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
class ProjectUrlMatcher extends Symfony\Component\Routing\Tests\Fixtures\RedirectableUrlMatcher
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
        $context = $this->context;
        $request = $this->request;

        // foo
        if (0 === strpos($pathinfo, '/foo') && preg_match('#^/foo/(?P<bar>baz|symfony)$#s', $pathinfo, $matches)) {
            $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'foo')), array (  'def' => 'test',));
            return $ret;
        }

        if (0 === strpos($pathinfo, '/bar')) {
            // bar
            if (preg_match('#^/bar/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
                if (!in_array($this->context->getMethod(), array('GET', 'HEAD'))) {
                    $allow = array_merge($allow, array('GET', 'HEAD'));
                    goto not_bar;
                }

                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'bar')), array ());
                return $ret;
            }
            not_bar:

            // barhead
            if (0 === strpos($pathinfo, '/barhead') && preg_match('#^/barhead/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
                if (!in_array($this->context->getMethod(), array('GET', 'HEAD'))) {
                    $allow = array_merge($allow, array('GET', 'HEAD'));
                    goto not_barhead;
                }

                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'barhead')), array ());
                return $ret;
            }
            not_barhead:

        }

        if (0 === strpos($pathinfo, '/test')) {
            if (0 === strpos($pathinfo, '/test/baz')) {
                // baz
                if ($pathinfo === '/test/baz') {
                    $ret = array('_route' => 'baz');
                    return $ret;
                }

                // baz2
                if ($pathinfo === '/test/baz.html') {
                    $ret = array('_route' => 'baz2');
                    return $ret;
                }

                // baz3
                if (rtrim($pathinfo, '/') === '/test/baz3') {
                    $ret = array('_route' => 'baz3');
                    if (substr($pathinfo, -1) !== '/') {
                        return array_replace($ret, $this->redirect($pathinfo.'/', 'baz3'));
                    }

                    return $ret;
                }

            }

            // baz4
            if (preg_match('#^/test/(?P<foo>[^/]++)/?$#s', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'baz4')), array ());
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($pathinfo.'/', 'baz4'));
                }

                return $ret;
            }

            // baz5
            if (preg_match('#^/test/(?P<foo>[^/]++)/$#s', $pathinfo, $matches)) {
                if ($this->context->getMethod() != 'POST') {
                    $allow[] = 'POST';
                    goto not_baz5;
                }

                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'baz5')), array ());
                return $ret;
            }
            not_baz5:

            // baz.baz6
            if (preg_match('#^/test/(?P<foo>[^/]++)/$#s', $pathinfo, $matches)) {
                if ($this->context->getMethod() != 'PUT') {
                    $allow[] = 'PUT';
                    goto not_bazbaz6;
                }

                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'baz.baz6')), array ());
                return $ret;
            }
            not_bazbaz6:

        }

        // foofoo
        if ($pathinfo === '/foofoo') {
            $ret = array (  'def' => 'test',  '_route' => 'foofoo',);
            return $ret;
        }

        // quoter
        if (preg_match('#^/(?P<quoter>[\']+)$#s', $pathinfo, $matches)) {
            $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'quoter')), array ());
            return $ret;
        }

        // space
        if ($pathinfo === '/spa ce') {
            $ret = array('_route' => 'space');
            return $ret;
        }

        if (0 === strpos($pathinfo, '/a')) {
            if (0 === strpos($pathinfo, '/a/b\'b')) {
                // foo1
                if (preg_match('#^/a/b\'b/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
                    $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'foo1')), array ());
                    return $ret;
                }

                // bar1
                if (preg_match('#^/a/b\'b/(?P<bar>[^/]++)$#s', $pathinfo, $matches)) {
                    $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'bar1')), array ());
                    return $ret;
                }

            }

            // overridden
            if (preg_match('#^/a/(?P<var>.*)$#s', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'overridden')), array ());
                return $ret;
            }

            if (0 === strpos($pathinfo, '/a/b\'b')) {
                // foo2
                if (preg_match('#^/a/b\'b/(?P<foo1>[^/]++)$#s', $pathinfo, $matches)) {
                    $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'foo2')), array ());
                    return $ret;
                }

                // bar2
                if (preg_match('#^/a/b\'b/(?P<bar1>[^/]++)$#s', $pathinfo, $matches)) {
                    $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'bar2')), array ());
                    return $ret;
                }

            }

        }

        if (0 === strpos($pathinfo, '/multi')) {
            // helloWorld
            if (0 === strpos($pathinfo, '/multi/hello') && preg_match('#^/multi/hello(?:/(?P<who>[^/]++))?$#s', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'helloWorld')), array (  'who' => 'World!',));
                return $ret;
            }

            // overridden2
            if ($pathinfo === '/multi/new') {
                $ret = array('_route' => 'overridden2');
                return $ret;
            }

            // hey
            if (rtrim($pathinfo, '/') === '/multi/hey') {
                $ret = array('_route' => 'hey');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($pathinfo.'/', 'hey'));
                }

                return $ret;
            }

        }

        // foo3
        if (preg_match('#^/(?P<_locale>[^/]++)/b/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
            $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'foo3')), array ());
            return $ret;
        }

        // bar3
        if (preg_match('#^/(?P<_locale>[^/]++)/b/(?P<bar>[^/]++)$#s', $pathinfo, $matches)) {
            $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'bar3')), array ());
            return $ret;
        }

        if (0 === strpos($pathinfo, '/aba')) {
            // ababa
            if ($pathinfo === '/ababa') {
                $ret = array('_route' => 'ababa');
                return $ret;
            }

            // foo4
            if (preg_match('#^/aba/(?P<foo>[^/]++)$#s', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'foo4')), array ());
                return $ret;
            }

        }

        $host = $this->context->getHost();

        if (preg_match('#^a\\.example\\.com$#si', $host, $hostMatches)) {
            // route1
            if ($pathinfo === '/route1') {
                $ret = array('_route' => 'route1');
                return $ret;
            }

            // route2
            if ($pathinfo === '/c2/route2') {
                $ret = array('_route' => 'route2');
                return $ret;
            }

        }

        if (preg_match('#^b\\.example\\.com$#si', $host, $hostMatches)) {
            // route3
            if ($pathinfo === '/c2/route3') {
                $ret = array('_route' => 'route3');
                return $ret;
            }

        }

        if (preg_match('#^a\\.example\\.com$#si', $host, $hostMatches)) {
            // route4
            if ($pathinfo === '/route4') {
                $ret = array('_route' => 'route4');
                return $ret;
            }

        }

        if (preg_match('#^c\\.example\\.com$#si', $host, $hostMatches)) {
            // route5
            if ($pathinfo === '/route5') {
                $ret = array('_route' => 'route5');
                return $ret;
            }

        }

        // route6
        if ($pathinfo === '/route6') {
            $ret = array('_route' => 'route6');
            return $ret;
        }

        if (preg_match('#^(?P<var1>[^\\.]++)\\.example\\.com$#si', $host, $hostMatches)) {
            if (0 === strpos($pathinfo, '/route1')) {
                // route11
                if ($pathinfo === '/route11') {
                    $ret = $this->mergeDefaults(array_replace($hostMatches, array('_route' => 'route11')), array ());
                    return $ret;
                }

                // route12
                if ($pathinfo === '/route12') {
                    $ret = $this->mergeDefaults(array_replace($hostMatches, array('_route' => 'route12')), array (  'var1' => 'val',));
                    return $ret;
                }

                // route13
                if (0 === strpos($pathinfo, '/route13') && preg_match('#^/route13/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                    $ret = $this->mergeDefaults(array_replace($hostMatches, $matches, array('_route' => 'route13')), array ());
                    return $ret;
                }

                // route14
                if (0 === strpos($pathinfo, '/route14') && preg_match('#^/route14/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                    $ret = $this->mergeDefaults(array_replace($hostMatches, $matches, array('_route' => 'route14')), array (  'var1' => 'val',));
                    return $ret;
                }

            }

        }

        if (preg_match('#^c\\.example\\.com$#si', $host, $hostMatches)) {
            // route15
            if (0 === strpos($pathinfo, '/route15') && preg_match('#^/route15/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'route15')), array ());
                return $ret;
            }

        }

        if (0 === strpos($pathinfo, '/route1')) {
            // route16
            if (0 === strpos($pathinfo, '/route16') && preg_match('#^/route16/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'route16')), array (  'var1' => 'val',));
                return $ret;
            }

            // route17
            if ($pathinfo === '/route17') {
                $ret = array('_route' => 'route17');
                return $ret;
            }

        }

        if (0 === strpos($pathinfo, '/a')) {
            // a
            if ($pathinfo === '/a/a...') {
                $ret = array('_route' => 'a');
                return $ret;
            }

            if (0 === strpos($pathinfo, '/a/b')) {
                // b
                if (preg_match('#^/a/b/(?P<var>[^/]++)$#s', $pathinfo, $matches)) {
                    $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'b')), array ());
                    return $ret;
                }

                // c
                if (0 === strpos($pathinfo, '/a/b/c') && preg_match('#^/a/b/c/(?P<var>[^/]++)$#s', $pathinfo, $matches)) {
                    $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'c')), array ());
                    return $ret;
                }

            }

        }

        // secure
        if ($pathinfo === '/secure') {
            $ret = array('_route' => 'secure');

            $requiredSchemes = array (  'https' => 0,);
            if (!isset($requiredSchemes[$this->context->getScheme()])) {
                return array_replace($ret, $this->redirect($pathinfo, 'secure', key($requiredSchemes)));
            }

            return $ret;
        }

        // nonsecure
        if ($pathinfo === '/nonsecure') {
            $ret = array('_route' => 'nonsecure');

            $requiredSchemes = array (  'http' => 0,);
            if (!isset($requiredSchemes[$this->context->getScheme()])) {
                return array_replace($ret, $this->redirect($pathinfo, 'nonsecure', key($requiredSchemes)));
            }

            return $ret;
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
