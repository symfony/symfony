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
    public function __construct(RequestContext $context, $charset)
    {
        $this->context = $context;
        $this->charset = $charset;
    }

    public function match($pathinfo)
    {
        $allow = array();
        $pathinfo = $this->toUtf8(rawurldecode($pathinfo));
        $context = $this->context;
        $request = $this->request;

        if (0 === strpos($pathinfo, '/rootprefix')) {
            // static
            if ($pathinfo === '/rootprefix/test') {
                return array('_route' => 'static');
            }

            // dynamic
            if (preg_match('#^/rootprefix/(?P<var>[^/]++)$#us', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'dynamic')), array ());
            }

        }

        // with-condition
        if ($pathinfo === '/with-condition' && ($context->getMethod() == "GET")) {
            return array('_route' => 'with-condition');
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
