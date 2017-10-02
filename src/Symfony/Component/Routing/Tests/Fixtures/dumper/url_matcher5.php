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
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'a_fourth');
                }

                return array('_route' => 'a_fourth');
            }

            // a_fifth
            if ('/a/55' === $trimmedPathinfo) {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'a_fifth');
                }

                return array('_route' => 'a_fifth');
            }

            // a_sixth
            if ('/a/66' === $trimmedPathinfo) {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'a_sixth');
                }

                return array('_route' => 'a_sixth');
            }

        }

        // nested_wildcard
        if (0 === strpos($pathinfo, '/nested') && preg_match('#^/nested/(?P<param>[^/]++)$#s', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'nested_wildcard')), array ());
        }

        if (0 === strpos($pathinfo, '/nested/group')) {
            // nested_a
            if ('/nested/group/a' === $trimmedPathinfo) {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'nested_a');
                }

                return array('_route' => 'nested_a');
            }

            // nested_b
            if ('/nested/group/b' === $trimmedPathinfo) {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'nested_b');
                }

                return array('_route' => 'nested_b');
            }

            // nested_c
            if ('/nested/group/c' === $trimmedPathinfo) {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'nested_c');
                }

                return array('_route' => 'nested_c');
            }

        }

        elseif (0 === strpos($pathinfo, '/slashed/group')) {
            // slashed_a
            if ('/slashed/group' === $trimmedPathinfo) {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'slashed_a');
                }

                return array('_route' => 'slashed_a');
            }

            // slashed_b
            if ('/slashed/group/b' === $trimmedPathinfo) {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'slashed_b');
                }

                return array('_route' => 'slashed_b');
            }

            // slashed_c
            if ('/slashed/group/c' === $trimmedPathinfo) {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'slashed_c');
                }

                return array('_route' => 'slashed_c');
            }

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
