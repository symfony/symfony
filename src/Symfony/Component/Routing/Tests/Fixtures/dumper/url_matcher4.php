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

        switch ($pathinfo) {
            case '/just_head':
                // just_head
                if ('HEAD' !== $requestMethod) {
                    $allow[] = 'HEAD';
                    goto not_just_head;
                }

                return array('_route' => 'just_head');
                not_just_head:
                break;
            case '/head_and_get':
                // head_and_get
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_head_and_get;
                }

                return array('_route' => 'head_and_get');
                not_head_and_get:
                break;
            case '/get_and_head':
                // get_and_head
                if ('GET' !== $canonicalMethod) {
                    $allow[] = 'GET';
                    goto not_get_and_head;
                }

                return array('_route' => 'get_and_head');
                not_get_and_head:
                break;
            case '/post_and_get':
                // post_and_head
                if (!in_array($requestMethod, array('POST', 'HEAD'))) {
                    $allow = array_merge($allow, array('POST', 'HEAD'));
                    goto not_post_and_head;
                }

                return array('_route' => 'post_and_head');
                not_post_and_head:
                break;
            case '/put_and_post':
                // put_and_post
                if (!in_array($requestMethod, array('PUT', 'POST'))) {
                    $allow = array_merge($allow, array('PUT', 'POST'));
                    goto not_put_and_post;
                }

                return array('_route' => 'put_and_post');
                not_put_and_post:
                // put_and_get_and_head
                if (!in_array($canonicalMethod, array('PUT', 'GET'))) {
                    $allow = array_merge($allow, array('PUT', 'GET'));
                    goto not_put_and_get_and_head;
                }

                return array('_route' => 'put_and_get_and_head');
                not_put_and_get_and_head:
                break;
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
