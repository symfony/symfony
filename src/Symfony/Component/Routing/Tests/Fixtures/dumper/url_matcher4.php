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
        $allow = $allowSchemes = array();
        $pathinfo = rawurldecode($rawPathinfo);
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($pathinfo) {
            case '/put_and_post':
                // put_and_post
                $ret = array('_route' => 'put_and_post');
                if (!isset(($a = array('PUT' => 0, 'POST' => 1))[$requestMethod])) {
                    $allow += $a;
                    goto not_put_and_post;
                }

                return $ret;
                not_put_and_post:
                // put_and_get_and_head
                $ret = array('_route' => 'put_and_get_and_head');
                if (!isset(($a = array('PUT' => 0, 'GET' => 1, 'HEAD' => 2))[$canonicalMethod])) {
                    $allow += $a;
                    goto not_put_and_get_and_head;
                }

                return $ret;
                not_put_and_get_and_head:
                break;
            default:
                $routes = array(
                    '/just_head' => array(array('_route' => 'just_head'), null, array('HEAD' => 0), null),
                    '/head_and_get' => array(array('_route' => 'head_and_get'), null, array('HEAD' => 0, 'GET' => 1), null),
                    '/get_and_head' => array(array('_route' => 'get_and_head'), null, array('GET' => 0, 'HEAD' => 1), null),
                    '/post_and_head' => array(array('_route' => 'post_and_head'), null, array('POST' => 0, 'HEAD' => 1), null),
                );

                if (!isset($routes[$pathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes) = $routes[$pathinfo];

                $hasRequiredScheme = !$requiredSchemes || isset($requiredSchemes[$context->getScheme()]);
                if ($requiredMethods && !isset($requiredMethods[$canonicalMethod]) && !isset($requiredMethods[$requestMethod])) {
                    if ($hasRequiredScheme) {
                        $allow += $requiredMethods;
                    }
                    break;
                }
                if (!$hasRequiredScheme) {
                    $allowSchemes += $requiredSchemes;
                    break;
                }

                return $ret;
        }

        if ('/' === $pathinfo && !$allow) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
