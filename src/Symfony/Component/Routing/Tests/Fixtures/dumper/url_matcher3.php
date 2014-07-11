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
        $context = $this->context;
        $request = $this->request;

        if (0 === strpos($pathinfo, '/rootprefix')) {
            // static
            if ($pathinfo === '/rootprefix/test') {
                $ret = array('_route' => 'static');
                return $ret;
            }

            // dynamic
            if (preg_match('#^/rootprefix/(?P<var>[^/]++)$#s', $pathinfo, $matches)) {
                $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'dynamic')), array ());
                return $ret;
            }

        }

        // with-condition
        if ($pathinfo === '/with-condition' && ($context->getMethod() == "GET")) {
            $ret = array('_route' => 'with-condition');
            return $ret;
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
