<?php

use Symfony\Component\Routing\Matcher\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Matcher\Exception\NotFoundException;

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
    public function __construct(array $context = array(), array $defaults = array())
    {
        $this->context = $context;
        $this->defaults = $defaults;
    }

    public function match($pathinfo)
    {
        $allow = array();

        // foo
        if (0 === strpos($pathinfo, '/foo') && preg_match('#^/foo/(?P<bar>baz|symfony)$#x', $pathinfo, $matches)) {
            return array_merge($this->mergeDefaults($matches, array (  'def' => 'test',)), array('_route' => 'foo'));
        }

        // bar
        if (0 === strpos($pathinfo, '/bar') && preg_match('#^/bar/(?P<foo>[^/\.]+?)$#x', $pathinfo, $matches)) {
            if (isset($this->context['method']) && !in_array(strtolower($this->context['method']), array('get', 'head'))) {
                $allow = array_merge($allow, array('get', 'head'));
                goto not_bar;
            }
            $matches['_route'] = 'bar';
            return $matches;
        }
        not_bar:

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
        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/\.]+?)/$#x', $pathinfo, $matches)) {
            $matches['_route'] = 'baz4';
            return $matches;
        }

        // baz5
        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/\.]+?)/$#x', $pathinfo, $matches)) {
            if (isset($this->context['method']) && !in_array(strtolower($this->context['method']), array('post'))) {
                $allow = array_merge($allow, array('post'));
                goto not_baz5;
            }
            $matches['_route'] = 'baz5';
            return $matches;
        }
        not_baz5:

        // baz.baz6
        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/\.]+?)/$#x', $pathinfo, $matches)) {
            if (isset($this->context['method']) && !in_array(strtolower($this->context['method']), array('put'))) {
                $allow = array_merge($allow, array('put'));
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

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new NotFoundException();
    }
}
