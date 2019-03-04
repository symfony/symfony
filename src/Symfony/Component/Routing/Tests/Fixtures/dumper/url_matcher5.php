<?php

use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Tests\Fixtures\RedirectableUrlMatcher
{
    use CompiledUrlMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->staticRoutes = [
            '/a/11' => [[['_route' => 'a_first'], null, null, null, false, false, null]],
            '/a/22' => [[['_route' => 'a_second'], null, null, null, false, false, null]],
            '/a/333' => [[['_route' => 'a_third'], null, null, null, false, false, null]],
            '/a/44' => [[['_route' => 'a_fourth'], null, null, null, true, false, null]],
            '/a/55' => [[['_route' => 'a_fifth'], null, null, null, true, false, null]],
            '/a/66' => [[['_route' => 'a_sixth'], null, null, null, true, false, null]],
            '/nested/group/a' => [[['_route' => 'nested_a'], null, null, null, true, false, null]],
            '/nested/group/b' => [[['_route' => 'nested_b'], null, null, null, true, false, null]],
            '/nested/group/c' => [[['_route' => 'nested_c'], null, null, null, true, false, null]],
            '/slashed/group' => [[['_route' => 'slashed_a'], null, null, null, true, false, null]],
            '/slashed/group/b' => [[['_route' => 'slashed_b'], null, null, null, true, false, null]],
            '/slashed/group/c' => [[['_route' => 'slashed_c'], null, null, null, true, false, null]],
        ];
        $this->regexpList = [
            0 => '{^(?'
                    .'|/([^/]++)(*:16)'
                    .'|/nested/([^/]++)(*:39)'
                .')/?$}sD',
        ];
        $this->dynamicRoutes = [
            16 => [[['_route' => 'a_wildcard'], ['param'], null, null, false, true, null]],
            39 => [
                [['_route' => 'nested_wildcard'], ['param'], null, null, false, true, null],
                [null, null, null, null, false, false, 0],
            ],
        ];
    }
}
