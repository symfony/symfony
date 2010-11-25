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

        if (isset($this->context['method']) && in_array(strtolower($this->context['method']), array (  0 => 'get',  1 => 'head',)) && 0 === strpos($url, '/bar') && preg_match('#^/bar/(?P<foo>[^/\.]+?)$#x', $url, $matches)) {
            return array_merge($this->mergeDefaults($matches, array ()), array('_route' => 'bar'));
        }

        return false;
    }
}
