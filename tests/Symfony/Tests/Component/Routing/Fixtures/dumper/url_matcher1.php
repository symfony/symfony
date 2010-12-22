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

    public function match($url)
    {
        $url = $this->normalizeUrl($url);

        if (0 === strpos($url, '/foo') && preg_match('#^/foo/(?P<bar>baz|symfony)$#x', $url, $matches)) {
            return array_merge($this->mergeDefaults($matches, array (  'def' => 'test',)), array('_route' => 'foo'));
        }

        if (isset($this->context['method']) && preg_match('#^(GET|head)$#xi', $this->context['method']) && 0 === strpos($url, '/bar') && preg_match('#^/bar/(?P<foo>[^/\.]+?)$#x', $url, $matches)) {
            return array_merge($this->mergeDefaults($matches, array ()), array('_route' => 'bar'));
        }

        if ($url === '/test/baz') {
            return array_merge($this->mergeDefaults(array(), array ()), array('_route' => 'baz'));
        }

        if ($url === '/test/baz.html') {
            return array_merge($this->mergeDefaults(array(), array ()), array('_route' => 'baz2'));
        }

        return false;
    }
}
