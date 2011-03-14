<?php

/**
 * ProjectUrlMatcher
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    /**
     * Constructor.
     */
    public function __construct(array $context = array(), array $defaults = array())
    {
        $this->context = $context;
        $this->defaults = $defaults;
    }

    public function match($pathinfo)
    {
        if (0 === strpos($pathinfo, '/foo') && preg_match('#^/foo/(?P<bar>baz|symfony)$#x', $pathinfo, $matches)) {
            return array_merge($this->mergeDefaults($matches, array (  'def' => 'test',)), array('_route' => 'foo'));
        }

        if (isset($this->context['method']) && preg_match('#^(GET|head)$#xi', $this->context['method']) && 0 === strpos($pathinfo, '/bar') && preg_match('#^/bar/(?P<foo>[^/\.]+?)$#x', $pathinfo, $matches)) {
            return array_merge($this->mergeDefaults($matches, array ()), array('_route' => 'bar'));
        }

        if ($pathinfo === '/test/baz') {
            return array_merge($this->mergeDefaults(array(), array ()), array('_route' => 'baz'));
        }

        if ($pathinfo === '/test/baz.html') {
            return array_merge($this->mergeDefaults(array(), array ()), array('_route' => 'baz2'));
        }

        if (rtrim($pathinfo, '/') === '/test/baz3') {
            if (substr($pathinfo, -1) !== '/') {
                return array('_controller' => 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction', 'url' => $this->context['base_url'].$pathinfo.'/', 'permanent' => true, '_route' => 'baz3');
            }
            return array_merge($this->mergeDefaults(array(), array ()), array('_route' => 'baz3'));
        }

        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/\.]+?)/?$#x', $pathinfo, $matches)) {
            if (substr($pathinfo, -1) !== '/') {
                return array('_controller' => 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction', 'url' => $this->context['base_url'].$pathinfo.'/', 'permanent' => true, '_route' => 'baz4');
            }
            return array_merge($this->mergeDefaults($matches, array ()), array('_route' => 'baz4'));
        }

        return false;
    }
}
