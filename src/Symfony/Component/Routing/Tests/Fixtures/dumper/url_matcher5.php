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


        if (0 === strpos($pathinfo, '/a/')) {
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

        if (0 === strpos($pathinfo, '/a/')) {
            // a_fourth
            if ('/a/44' === $pathinfo) {
                return array('_route' => 'a_fourth');
            }

            // a_fifth
            if ('/a/55' === $pathinfo) {
                return array('_route' => 'a_fifth');
            }

            // a_sixth
            if ('/a/66' === $pathinfo) {
                return array('_route' => 'a_sixth');
            }

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
