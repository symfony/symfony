<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
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

        if (0 === strpos($pathinfo, '/trailing/simple')) {
            // simple_trailing_slash_no_methods
            if ('/trailing/simple/no-methods/' === $pathinfo) {
                return array('_route' => 'simple_trailing_slash_no_methods');
            }

            // simple_trailing_slash_GET_method
            if ('/trailing/simple/get-method/' === $pathinfo) {
                $ret = array('_route' => 'simple_trailing_slash_GET_method');
                if (!in_array($canonicalMethod, array('GET'))) {
                    $allow = array_merge($allow, array('GET'));
                    goto not_simple_trailing_slash_GET_method;
                }

                return $ret;
            }
            not_simple_trailing_slash_GET_method:

            // simple_trailing_slash_HEAD_method
            if ('/trailing/simple/head-method/' === $pathinfo) {
                $ret = array('_route' => 'simple_trailing_slash_HEAD_method');
                if (!in_array($requestMethod, array('HEAD'))) {
                    $allow = array_merge($allow, array('HEAD'));
                    goto not_simple_trailing_slash_HEAD_method;
                }

                return $ret;
            }
            not_simple_trailing_slash_HEAD_method:

            // simple_trailing_slash_POST_method
            if ('/trailing/simple/post-method/' === $pathinfo) {
                $ret = array('_route' => 'simple_trailing_slash_POST_method');
                if (!in_array($requestMethod, array('POST'))) {
                    $allow = array_merge($allow, array('POST'));
                    goto not_simple_trailing_slash_POST_method;
                }

                return $ret;
            }
            not_simple_trailing_slash_POST_method:

        }

        elseif (0 === strpos($pathinfo, '/trailing/regex')) {
            // regex_trailing_slash_no_methods
            if (0 === strpos($pathinfo, '/trailing/regex/no-methods') && preg_match('#^/trailing/regex/no\\-methods/(?P<param>[^/]++)/$#sD', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_trailing_slash_no_methods')), array ());
            }

            // regex_trailing_slash_GET_method
            if (0 === strpos($pathinfo, '/trailing/regex/get-method') && preg_match('#^/trailing/regex/get\\-method/(?P<param>[^/]++)/$#sD', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_trailing_slash_GET_method')), array ());
                if (!in_array($canonicalMethod, array('GET'))) {
                    $allow = array_merge($allow, array('GET'));
                    goto not_regex_trailing_slash_GET_method;
                }

                return $ret;
            }
            not_regex_trailing_slash_GET_method:

            // regex_trailing_slash_HEAD_method
            if (0 === strpos($pathinfo, '/trailing/regex/head-method') && preg_match('#^/trailing/regex/head\\-method/(?P<param>[^/]++)/$#sD', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_trailing_slash_HEAD_method')), array ());
                if (!in_array($requestMethod, array('HEAD'))) {
                    $allow = array_merge($allow, array('HEAD'));
                    goto not_regex_trailing_slash_HEAD_method;
                }

                return $ret;
            }
            not_regex_trailing_slash_HEAD_method:

            // regex_trailing_slash_POST_method
            if (0 === strpos($pathinfo, '/trailing/regex/post-method') && preg_match('#^/trailing/regex/post\\-method/(?P<param>[^/]++)/$#sD', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_trailing_slash_POST_method')), array ());
                if (!in_array($requestMethod, array('POST'))) {
                    $allow = array_merge($allow, array('POST'));
                    goto not_regex_trailing_slash_POST_method;
                }

                return $ret;
            }
            not_regex_trailing_slash_POST_method:

        }

        elseif (0 === strpos($pathinfo, '/not-trailing/simple')) {
            // simple_not_trailing_slash_no_methods
            if ('/not-trailing/simple/no-methods' === $pathinfo) {
                return array('_route' => 'simple_not_trailing_slash_no_methods');
            }

            // simple_not_trailing_slash_GET_method
            if ('/not-trailing/simple/get-method' === $pathinfo) {
                $ret = array('_route' => 'simple_not_trailing_slash_GET_method');
                if (!in_array($canonicalMethod, array('GET'))) {
                    $allow = array_merge($allow, array('GET'));
                    goto not_simple_not_trailing_slash_GET_method;
                }

                return $ret;
            }
            not_simple_not_trailing_slash_GET_method:

            // simple_not_trailing_slash_HEAD_method
            if ('/not-trailing/simple/head-method' === $pathinfo) {
                $ret = array('_route' => 'simple_not_trailing_slash_HEAD_method');
                if (!in_array($requestMethod, array('HEAD'))) {
                    $allow = array_merge($allow, array('HEAD'));
                    goto not_simple_not_trailing_slash_HEAD_method;
                }

                return $ret;
            }
            not_simple_not_trailing_slash_HEAD_method:

            // simple_not_trailing_slash_POST_method
            if ('/not-trailing/simple/post-method' === $pathinfo) {
                $ret = array('_route' => 'simple_not_trailing_slash_POST_method');
                if (!in_array($requestMethod, array('POST'))) {
                    $allow = array_merge($allow, array('POST'));
                    goto not_simple_not_trailing_slash_POST_method;
                }

                return $ret;
            }
            not_simple_not_trailing_slash_POST_method:

        }

        elseif (0 === strpos($pathinfo, '/not-trailing/regex')) {
            // regex_not_trailing_slash_no_methods
            if (0 === strpos($pathinfo, '/not-trailing/regex/no-methods') && preg_match('#^/not\\-trailing/regex/no\\-methods/(?P<param>[^/]++)$#sD', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_not_trailing_slash_no_methods')), array ());
            }

            // regex_not_trailing_slash_GET_method
            if (0 === strpos($pathinfo, '/not-trailing/regex/get-method') && preg_match('#^/not\\-trailing/regex/get\\-method/(?P<param>[^/]++)$#sD', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_not_trailing_slash_GET_method')), array ());
                if (!in_array($canonicalMethod, array('GET'))) {
                    $allow = array_merge($allow, array('GET'));
                    goto not_regex_not_trailing_slash_GET_method;
                }

                return $ret;
            }
            not_regex_not_trailing_slash_GET_method:

            // regex_not_trailing_slash_HEAD_method
            if (0 === strpos($pathinfo, '/not-trailing/regex/head-method') && preg_match('#^/not\\-trailing/regex/head\\-method/(?P<param>[^/]++)$#sD', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_not_trailing_slash_HEAD_method')), array ());
                if (!in_array($requestMethod, array('HEAD'))) {
                    $allow = array_merge($allow, array('HEAD'));
                    goto not_regex_not_trailing_slash_HEAD_method;
                }

                return $ret;
            }
            not_regex_not_trailing_slash_HEAD_method:

            // regex_not_trailing_slash_POST_method
            if (0 === strpos($pathinfo, '/not-trailing/regex/post-method') && preg_match('#^/not\\-trailing/regex/post\\-method/(?P<param>[^/]++)$#sD', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_not_trailing_slash_POST_method')), array ());
                if (!in_array($requestMethod, array('POST'))) {
                    $allow = array_merge($allow, array('POST'));
                    goto not_regex_not_trailing_slash_POST_method;
                }

                return $ret;
            }
            not_regex_not_trailing_slash_POST_method:

        }

        if ('/' === $pathinfo) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
