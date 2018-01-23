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
        $request = $this->request;
        $requestMethod = $canonicalMethod = $context->getMethod();
        $scheme = $context->getScheme();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }


        if (0 === strpos($pathinfo, '/a')) {
            // a_first
            if ('/a/11' === $pathinfo) {
                return array('_route' => 'a_first');
            }

            // a_second
            if ('/a/22' === $pathinfo) {
                return array('_route' => 'a_second');
            }

            // a_third
            if ('/a/333' === $pathinfo) {
                return array('_route' => 'a_third');
            }

        }

        // a_wildcard
        if (preg_match('#^/(?P<param>[^/]++)$#s', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'a_wildcard')), array ());
        }

        if (0 === strpos($pathinfo, '/a')) {
            // a_fourth
            if ('/a/44' === $trimmedPathinfo) {
                $ret = array('_route' => 'a_fourth');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'a_fourth'));
                }

                return $ret;
            }

            // a_fifth
            if ('/a/55' === $trimmedPathinfo) {
                $ret = array('_route' => 'a_fifth');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'a_fifth'));
                }

                return $ret;
            }

            // a_sixth
            if ('/a/66' === $trimmedPathinfo) {
                $ret = array('_route' => 'a_sixth');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'a_sixth'));
                }

                return $ret;
            }

        }

        // nested_wildcard
        if (0 === strpos($pathinfo, '/nested') && preg_match('#^/nested/(?P<param>[^/]++)$#s', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'nested_wildcard')), array ());
        }

        if (0 === strpos($pathinfo, '/nested/group')) {
            // nested_a
            if ('/nested/group/a' === $trimmedPathinfo) {
                $ret = array('_route' => 'nested_a');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'nested_a'));
                }

                return $ret;
            }

            // nested_b
            if ('/nested/group/b' === $trimmedPathinfo) {
                $ret = array('_route' => 'nested_b');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'nested_b'));
                }

                return $ret;
            }

            // nested_c
            if ('/nested/group/c' === $trimmedPathinfo) {
                $ret = array('_route' => 'nested_c');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'nested_c'));
                }

                return $ret;
            }

        }

        elseif (0 === strpos($pathinfo, '/slashed/group')) {
            // slashed_a
            if ('/slashed/group' === $trimmedPathinfo) {
                $ret = array('_route' => 'slashed_a');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'slashed_a'));
                }

                return $ret;
            }

            // slashed_b
            if ('/slashed/group/b' === $trimmedPathinfo) {
                $ret = array('_route' => 'slashed_b');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'slashed_b'));
                }

                return $ret;
            }

            // slashed_c
            if ('/slashed/group/c' === $trimmedPathinfo) {
                $ret = array('_route' => 'slashed_c');
                if (substr($pathinfo, -1) !== '/') {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'slashed_c'));
                }

                return $ret;
            }

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
