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


        if (0 === strpos($pathinfo, '/trailing/simple')) {
            // simple_trailing_slash_no_methods
            if ('/trailing/simple/no-methods/' === $pathinfo) {
                return array('_route' => 'simple_trailing_slash_no_methods');
            }

            // simple_trailing_slash_GET_method
            if ('/trailing/simple/get-method/' === $pathinfo) {
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_simple_trailing_slash_GET_method;
                }

                return array('_route' => 'simple_trailing_slash_GET_method');
            }
            not_simple_trailing_slash_GET_method:

            // simple_trailing_slash_HEAD_method
            if ('/trailing/simple/head-method/' === $pathinfo) {
                if ('HEAD' !== $requestMethod) {
                    $allow[] = 'HEAD';
                    goto not_simple_trailing_slash_HEAD_method;
                }

                return array('_route' => 'simple_trailing_slash_HEAD_method');
            }
            not_simple_trailing_slash_HEAD_method:

            // simple_trailing_slash_POST_method
            if ('/trailing/simple/post-method/' === $pathinfo) {
                if ('POST' !== $canonicalMethod) {
                    $allow[] = 'POST';
                    goto not_simple_trailing_slash_POST_method;
                }

                return array('_route' => 'simple_trailing_slash_POST_method');
            }
            not_simple_trailing_slash_POST_method:

        }

        elseif (0 === strpos($pathinfo, '/trailing/regex')) {
            // regex_trailing_slash_no_methods
            if (0 === strpos($pathinfo, '/trailing/regex/no-methods') && preg_match('#^/trailing/regex/no\\-methods/(?P<param>[^/]++)/$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_trailing_slash_no_methods')), array ());
            }

            // regex_trailing_slash_GET_method
            if (0 === strpos($pathinfo, '/trailing/regex/get-method') && preg_match('#^/trailing/regex/get\\-method/(?P<param>[^/]++)/$#s', $pathinfo, $matches)) {
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_regex_trailing_slash_GET_method;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_trailing_slash_GET_method')), array ());
            }
            not_regex_trailing_slash_GET_method:

            // regex_trailing_slash_HEAD_method
            if (0 === strpos($pathinfo, '/trailing/regex/head-method') && preg_match('#^/trailing/regex/head\\-method/(?P<param>[^/]++)/$#s', $pathinfo, $matches)) {
                if ('HEAD' !== $requestMethod) {
                    $allow[] = 'HEAD';
                    goto not_regex_trailing_slash_HEAD_method;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_trailing_slash_HEAD_method')), array ());
            }
            not_regex_trailing_slash_HEAD_method:

            // regex_trailing_slash_POST_method
            if (0 === strpos($pathinfo, '/trailing/regex/post-method') && preg_match('#^/trailing/regex/post\\-method/(?P<param>[^/]++)/$#s', $pathinfo, $matches)) {
                if ('POST' !== $canonicalMethod) {
                    $allow[] = 'POST';
                    goto not_regex_trailing_slash_POST_method;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_trailing_slash_POST_method')), array ());
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
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_simple_not_trailing_slash_GET_method;
                }

                return array('_route' => 'simple_not_trailing_slash_GET_method');
            }
            not_simple_not_trailing_slash_GET_method:

            // simple_not_trailing_slash_HEAD_method
            if ('/not-trailing/simple/head-method' === $pathinfo) {
                if ('HEAD' !== $requestMethod) {
                    $allow[] = 'HEAD';
                    goto not_simple_not_trailing_slash_HEAD_method;
                }

                return array('_route' => 'simple_not_trailing_slash_HEAD_method');
            }
            not_simple_not_trailing_slash_HEAD_method:

            // simple_not_trailing_slash_POST_method
            if ('/not-trailing/simple/post-method' === $pathinfo) {
                if ('POST' !== $canonicalMethod) {
                    $allow[] = 'POST';
                    goto not_simple_not_trailing_slash_POST_method;
                }

                return array('_route' => 'simple_not_trailing_slash_POST_method');
            }
            not_simple_not_trailing_slash_POST_method:

        }

        elseif (0 === strpos($pathinfo, '/not-trailing/regex')) {
            // regex_not_trailing_slash_no_methods
            if (0 === strpos($pathinfo, '/not-trailing/regex/no-methods') && preg_match('#^/not\\-trailing/regex/no\\-methods/(?P<param>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_not_trailing_slash_no_methods')), array ());
            }

            // regex_not_trailing_slash_GET_method
            if (0 === strpos($pathinfo, '/not-trailing/regex/get-method') && preg_match('#^/not\\-trailing/regex/get\\-method/(?P<param>[^/]++)$#s', $pathinfo, $matches)) {
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_regex_not_trailing_slash_GET_method;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_not_trailing_slash_GET_method')), array ());
            }
            not_regex_not_trailing_slash_GET_method:

            // regex_not_trailing_slash_HEAD_method
            if (0 === strpos($pathinfo, '/not-trailing/regex/head-method') && preg_match('#^/not\\-trailing/regex/head\\-method/(?P<param>[^/]++)$#s', $pathinfo, $matches)) {
                if ('HEAD' !== $requestMethod) {
                    $allow[] = 'HEAD';
                    goto not_regex_not_trailing_slash_HEAD_method;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_not_trailing_slash_HEAD_method')), array ());
            }
            not_regex_not_trailing_slash_HEAD_method:

            // regex_not_trailing_slash_POST_method
            if (0 === strpos($pathinfo, '/not-trailing/regex/post-method') && preg_match('#^/not\\-trailing/regex/post\\-method/(?P<param>[^/]++)$#s', $pathinfo, $matches)) {
                if ('POST' !== $canonicalMethod) {
                    $allow[] = 'POST';
                    goto not_regex_not_trailing_slash_POST_method;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'regex_not_trailing_slash_POST_method')), array ());
            }
            not_regex_not_trailing_slash_POST_method:

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
