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

        // just_head
        if ('/just_head' === $pathinfo) {
            $ret = array('_route' => 'just_head');
            if (!in_array($requestMethod, array('HEAD'))) {
                $allow = array_merge($allow, array('HEAD'));
                goto not_just_head;
            }

            return $ret;
        }
        not_just_head:

        // head_and_get
        if ('/head_and_get' === $pathinfo) {
            $ret = array('_route' => 'head_and_get');
            if (!in_array($canonicalMethod, array('HEAD', 'GET'))) {
                $allow = array_merge($allow, array('HEAD', 'GET'));
                goto not_head_and_get;
            }

            return $ret;
        }
        not_head_and_get:

        // get_and_head
        if ('/get_and_head' === $pathinfo) {
            $ret = array('_route' => 'get_and_head');
            if (!in_array($canonicalMethod, array('GET', 'HEAD'))) {
                $allow = array_merge($allow, array('GET', 'HEAD'));
                goto not_get_and_head;
            }

            return $ret;
        }
        not_get_and_head:

        // post_and_head
        if ('/post_and_head' === $pathinfo) {
            $ret = array('_route' => 'post_and_head');
            if (!in_array($requestMethod, array('POST', 'HEAD'))) {
                $allow = array_merge($allow, array('POST', 'HEAD'));
                goto not_post_and_head;
            }

            return $ret;
        }
        not_post_and_head:

        if (0 === strpos($pathinfo, '/put_and_post')) {
            // put_and_post
            if ('/put_and_post' === $pathinfo) {
                $ret = array('_route' => 'put_and_post');
                if (!in_array($requestMethod, array('PUT', 'POST'))) {
                    $allow = array_merge($allow, array('PUT', 'POST'));
                    goto not_put_and_post;
                }

                return $ret;
            }
            not_put_and_post:

            // put_and_get_and_head
            if ('/put_and_post' === $pathinfo) {
                $ret = array('_route' => 'put_and_get_and_head');
                if (!in_array($canonicalMethod, array('PUT', 'GET', 'HEAD'))) {
                    $allow = array_merge($allow, array('PUT', 'GET', 'HEAD'));
                    goto not_put_and_get_and_head;
                }

                return $ret;
            }
            not_put_and_get_and_head:

        }

        if ('/' === $pathinfo) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
