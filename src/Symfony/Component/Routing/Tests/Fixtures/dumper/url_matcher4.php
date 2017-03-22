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
        $pathinfo = rawurldecode($pathinfo);
        $trimmedPathinfo = rtrim($pathinfo, '/');
        $context = $this->context;
        $request = $this->request;
        $requestMethod = $canonicalMethod = $context->getMethod();
        $scheme = $context->getScheme();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }


        // just_head
        if ('/just_head' === $pathinfo) {
            if ('HEAD' !== $requestMethod) {
                $allow[] = 'HEAD';
                goto not_just_head;
            }

            return array('_route' => 'just_head');
        }
        not_just_head:

        // head_and_get
        if ('/head_and_get' === $pathinfo) {
            if ('GET' !== $canonicalMethod) {
                $allow[] = 'GET';
                goto not_head_and_get;
            }

            return array('_route' => 'head_and_get');
        }
        not_head_and_get:

        // post_and_head
        if ('/post_and_get' === $pathinfo) {
            if (!in_array($requestMethod, array('POST', 'HEAD'))) {
                $allow = array_merge($allow, array('POST', 'HEAD'));
                goto not_post_and_head;
            }

            return array('_route' => 'post_and_head');
        }
        not_post_and_head:

        if (0 === strpos($pathinfo, '/put_and_post')) {
            // put_and_post
            if ('/put_and_post' === $pathinfo) {
                if (!in_array($requestMethod, array('PUT', 'POST'))) {
                    $allow = array_merge($allow, array('PUT', 'POST'));
                    goto not_put_and_post;
                }

                return array('_route' => 'put_and_post');
            }
            not_put_and_post:

            // put_and_get_and_head
            if ('/put_and_post' === $pathinfo) {
                if (!in_array($canonicalMethod, array('PUT', 'GET'))) {
                    $allow = array_merge($allow, array('PUT', 'GET'));
                    goto not_put_and_get_and_head;
                }

                return array('_route' => 'put_and_get_and_head');
            }
            not_put_and_get_and_head:

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
