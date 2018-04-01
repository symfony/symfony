<?php

use Symphony\Component\Routing\Exception\MethodNotAllowedException;
use Symphony\Component\Routing\Exception\ResourceNotFoundException;
use Symphony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symphony Routing Component.
 */
class ProjectUrlMatcher extends Symphony\Component\Routing\Matcher\UrlMatcher
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

        if ('/' === $pathinfo) {
            throw new Symphony\Component\Routing\Exception\NoConfigurationException();
        }

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
