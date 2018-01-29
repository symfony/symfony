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
            case '/a/11':
                // a_first
                return array('_route' => 'a_first');
                break;
            case '/a/22':
                // a_second
                return array('_route' => 'a_second');
                break;
            case '/a/333':
                // a_third
                return array('_route' => 'a_third');
                break;
            case '/a/44':
                // a_fourth
                $ret = array('_route' => 'a_fourth');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_a_fourth;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'a_fourth'));
                }

                return $ret;
                not_a_fourth:
                break;
            case '/a/55':
                // a_fifth
                $ret = array('_route' => 'a_fifth');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_a_fifth;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'a_fifth'));
                }

                return $ret;
                not_a_fifth:
                break;
            case '/a/66':
                // a_sixth
                $ret = array('_route' => 'a_sixth');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_a_sixth;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'a_sixth'));
                }

                return $ret;
                not_a_sixth:
                break;
            case '/nested/group/a':
                // nested_a
                $ret = array('_route' => 'nested_a');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_nested_a;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'nested_a'));
                }

                return $ret;
                not_nested_a:
                break;
            case '/nested/group/b':
                // nested_b
                $ret = array('_route' => 'nested_b');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_nested_b;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'nested_b'));
                }

                return $ret;
                not_nested_b:
                break;
            case '/nested/group/c':
                // nested_c
                $ret = array('_route' => 'nested_c');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_nested_c;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'nested_c'));
                }

                return $ret;
                not_nested_c:
                break;
            case '/slashed/group':
                // slashed_a
                $ret = array('_route' => 'slashed_a');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_slashed_a;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'slashed_a'));
                }

                return $ret;
                not_slashed_a:
                break;
            case '/slashed/group/b':
                // slashed_b
                $ret = array('_route' => 'slashed_b');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_slashed_b;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'slashed_b'));
                }

                return $ret;
                not_slashed_b:
                break;
            case '/slashed/group/c':
                // slashed_c
                $ret = array('_route' => 'slashed_c');
                if ('/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    goto not_slashed_c;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', 'slashed_c'));
                }

                return $ret;
                not_slashed_c:
                break;
        }

        // a_wildcard
        if (preg_match('#^/(?P<param>[^/]++)$#sD', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'a_wildcard')), array());
        }

        // nested_wildcard
        if (0 === strpos($pathinfo, '/nested') && preg_match('#^/nested/(?P<param>[^/]++)$#sD', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'nested_wildcard')), array());
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
