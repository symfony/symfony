<?php

use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    use CompiledUrlMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->regexpList = [
            0 => '{^(?'
                    .'|/(a)(*:11)'
                .')/?$}sD',
            11 => '{^(?'
                    .'|/(.)(*:22)'
                .')/?$}sDu',
            22 => '{^(?'
                    .'|/(.)(*:33)'
                .')/?$}sD',
        ];
        $this->dynamicRoutes = [
            11 => [[['_route' => 'a'], ['a'], null, null, false, true, null]],
            22 => [[['_route' => 'b'], ['a'], null, null, false, true, null]],
            33 => [
                [['_route' => 'c'], ['a'], null, null, false, true, null],
                [null, null, null, null, false, false, 0],
            ],
        ];
    }
}
