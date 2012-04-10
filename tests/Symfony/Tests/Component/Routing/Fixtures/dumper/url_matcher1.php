<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * ProjectUrlMatcher
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
        $pathinfo = urldecode($pathinfo);

        // foo
        if (0 === strpos($pathinfo, '/foo') && preg_match('#^/foo/(?P<bar>baz|symfony)$#s', $pathinfo, $matches)) {
            return array_merge($this->mergeDefaults($matches, array (  'def' => 'test',)), array('_route' => 'foo'));
        }

        // bar
        if (0 === strpos($pathinfo, '/bar') && preg_match('#^/bar/(?P<foo>[^/]+?)$#s', $pathinfo, $matches)) {
            if (!in_array($this->context->getMethod(), array('GET', 'HEAD'))) {
                $allow = array_merge($allow, array('GET', 'HEAD'));
                goto not_bar;
            }
            $matches['_route'] = 'bar';
            return $matches;
        }
        not_bar:

        // barhead
        if (0 === strpos($pathinfo, '/barhead') && preg_match('#^/barhead/(?P<foo>[^/]+?)$#s', $pathinfo, $matches)) {
            if (!in_array($this->context->getMethod(), array('GET', 'HEAD'))) {
                $allow = array_merge($allow, array('GET', 'HEAD'));
                goto not_barhead;
            }
            $matches['_route'] = 'barhead';
            return $matches;
        }
        not_barhead:

        // baz
        if ($pathinfo === '/test/baz') {
            return array('_route' => 'baz');
        }

        // baz2
        if ($pathinfo === '/test/baz.html') {
            return array('_route' => 'baz2');
        }

        // baz3
        if ($pathinfo === '/test/baz3/') {
            return array('_route' => 'baz3');
        }

        // baz4
        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/]+?)/$#s', $pathinfo, $matches)) {
            $matches['_route'] = 'baz4';
            return $matches;
        }

        // baz5
        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/]+?)/$#s', $pathinfo, $matches)) {
            if ($this->context->getMethod() != 'POST') {
                $allow[] = 'POST';
                goto not_baz5;
            }
            $matches['_route'] = 'baz5';
            return $matches;
        }
        not_baz5:

        // baz.baz6
        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/]+?)/$#s', $pathinfo, $matches)) {
            if ($this->context->getMethod() != 'PUT') {
                $allow[] = 'PUT';
                goto not_bazbaz6;
            }
            $matches['_route'] = 'baz.baz6';
            return $matches;
        }
        not_bazbaz6:

        // foofoo
        if ($pathinfo === '/foofoo') {
            return array (  'def' => 'test',  '_route' => 'foofoo',);
        }

        // quoter
        if (preg_match('#^/(?P<quoter>[\']+)$#s', $pathinfo, $matches)) {
            $matches['_route'] = 'quoter';
            return $matches;
        }

        // space
        if ($pathinfo === '/spa ce') {
            return array('_route' => 'space');
        }

        if (0 === strpos($pathinfo, '/a')) {
            if (0 === strpos($pathinfo, '/a/b\'b')) {
                // foo1
                if (preg_match('#^/a/b\'b/(?P<foo>[^/]+?)$#s', $pathinfo, $matches)) {
                    $matches['_route'] = 'foo1';
                    return $matches;
                }

                // bar1
                if (preg_match('#^/a/b\'b/(?P<bar>[^/]+?)$#s', $pathinfo, $matches)) {
                    $matches['_route'] = 'bar1';
                    return $matches;
                }

                // foo2
                if (preg_match('#^/a/b\'b/(?P<foo1>[^/]+?)$#s', $pathinfo, $matches)) {
                    $matches['_route'] = 'foo2';
                    return $matches;
                }

                // bar2
                if (preg_match('#^/a/b\'b/(?P<bar1>[^/]+?)$#s', $pathinfo, $matches)) {
                    $matches['_route'] = 'bar2';
                    return $matches;
                }

            }

            // overriden
            if ($pathinfo === '/a/overriden2') {
                return array('_route' => 'overriden');
            }

            // ababa
            if ($pathinfo === '/ababa') {
                return array('_route' => 'ababa');
            }

            // foo4
            if (preg_match('#^/aba/(?P<foo>[^/]+?)$#s', $pathinfo, $matches)) {
                $matches['_route'] = 'foo4';
                return $matches;
            }

        }

        // foo3
        if (preg_match('#^/(?P<_locale>[^/]+?)/b/(?P<foo>[^/]+?)$#s', $pathinfo, $matches)) {
            $matches['_route'] = 'foo3';
            return $matches;
        }

        // bar3
        if (preg_match('#^/(?P<_locale>[^/]+?)/b/(?P<bar>[^/]+?)$#s', $pathinfo, $matches)) {
            $matches['_route'] = 'bar3';
            return $matches;
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
