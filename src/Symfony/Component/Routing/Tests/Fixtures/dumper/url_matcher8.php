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
        $this->regexpList = array(
            0 => '{^(?'
                    .'|/(a)(*:11)'
                .')$}sD',
            11 => '{^(?'
                    .'|/(.)(*:22)'
                .')$}sDu',
            22 => '{^(?'
                    .'|/(.)(*:33)'
                .')$}sD',
        );
        $this->dynamicRoutes = array(
            11 => array(array(array('_route' => 'a'), array('a'), null, null, null)),
            22 => array(array(array('_route' => 'b'), array('a'), null, null, null)),
            33 => array(array(array('_route' => 'c'), array('a'), null, null, null)),
        );
    }
}
