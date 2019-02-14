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
        $this->matchHost = true;
        $this->regexpList = [
            0 => '{^(?'
                .'|(?i:([^\\.]++)\\.exampple\\.com)\\.(?'
                    .'|/abc([^/]++)(?'
                        .'|(*:56)'
                    .')'
                .')'
                .')/?$}sD',
        ];
        $this->dynamicRoutes = [
            56 => [
                [['_route' => 'r1'], ['foo', 'foo'], null, null, false, true, null],
                [['_route' => 'r2'], ['foo', 'foo'], null, null, false, true, null],
                [null, null, null, null, false, false, 0],
            ],
        ];
    }
}
