<?php

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    use PhpMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->staticRoutes = array(
            '/rootprefix/test' => array(array(array('_route' => 'static'), null, null, null, false, false, null)),
            '/with-condition' => array(array(array('_route' => 'with-condition'), null, null, null, false, false, -1)),
        );
        $this->regexpList = array(
            0 => '{^(?'
                    .'|/rootprefix/([^/]++)(*:27)'
                .')/?$}sD',
        );
        $this->dynamicRoutes = array(
            27 => array(array(array('_route' => 'dynamic'), array('var'), null, null, false, true, null)),
        );
        $this->checkCondition = static function ($condition, $context, $request) {
            switch ($condition) {
                case -1: return ($context->getMethod() == "GET");
            }
        };
    }
}
