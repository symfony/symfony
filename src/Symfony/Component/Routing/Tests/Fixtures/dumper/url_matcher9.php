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
        $allow = $allowSchemes = array();
        $pathinfo = rawurldecode($pathinfo) ?: '/';
        $trimmedPathinfo = rtrim($pathinfo, '/') ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();
        $host = strtolower($context->getHost());

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($trimmedPathinfo) {
            case '/':
                // a
                if (preg_match('#^(?P<d>[^\\.]++)\\.e\\.c\\.b\\.a$#sDi', $host, $hostMatches)) {
                    return $this->mergeDefaults(array('_route' => 'a') + $hostMatches, array());
                }
                not_a:
                // c
                if (preg_match('#^(?P<e>[^\\.]++)\\.e\\.c\\.b\\.a$#sDi', $host, $hostMatches)) {
                    return $this->mergeDefaults(array('_route' => 'c') + $hostMatches, array());
                }
                not_c:
                // b
                if ('d.c.b.a' === $host) {
                    return array('_route' => 'b');
                }
                not_b:
                break;
        }

        if ('/' === $pathinfo && !$allow && !$allowSchemes) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
