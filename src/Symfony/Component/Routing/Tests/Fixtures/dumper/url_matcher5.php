<?php

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Tests\Fixtures\RedirectableUrlMatcher
{
    use PhpMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->staticRoutes = array(
            '/a/11' => array(array(array('_route' => 'a_first'), null, null, null, null)),
            '/a/22' => array(array(array('_route' => 'a_second'), null, null, null, null)),
            '/a/333' => array(array(array('_route' => 'a_third'), null, null, null, null)),
            '/a/44/' => array(array(array('_route' => 'a_fourth'), null, null, null, null)),
            '/a/55/' => array(array(array('_route' => 'a_fifth'), null, null, null, null)),
            '/a/66/' => array(array(array('_route' => 'a_sixth'), null, null, null, null)),
            '/nested/group/a/' => array(array(array('_route' => 'nested_a'), null, null, null, null)),
            '/nested/group/b/' => array(array(array('_route' => 'nested_b'), null, null, null, null)),
            '/nested/group/c/' => array(array(array('_route' => 'nested_c'), null, null, null, null)),
            '/slashed/group/' => array(array(array('_route' => 'slashed_a'), null, null, null, null)),
            '/slashed/group/b/' => array(array(array('_route' => 'slashed_b'), null, null, null, null)),
            '/slashed/group/c/' => array(array(array('_route' => 'slashed_c'), null, null, null, null)),
        );
        $this->regexpList = array(
            0 => '{^(?'
                    .'|/([^/]++)(*:16)'
                    .'|/nested/([^/]++)(*:39)'
                .')$}sD',
        );
        $this->dynamicRoutes = array(
            16 => array(array(array('_route' => 'a_wildcard'), array('param'), null, null, null)),
            39 => array(array(array('_route' => 'nested_wildcard'), array('param'), null, null, null)),
        );
    }
}
