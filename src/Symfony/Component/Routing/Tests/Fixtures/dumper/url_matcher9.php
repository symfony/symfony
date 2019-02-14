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
        $this->staticRoutes = [
            '/' => [
                [['_route' => 'a'], '#^(?P<d>[^\\.]++)\\.e\\.c\\.b\\.a$#sDi', null, null, false, false, null],
                [['_route' => 'c'], '#^(?P<e>[^\\.]++)\\.e\\.c\\.b\\.a$#sDi', null, null, false, false, null],
                [['_route' => 'b'], 'd.c.b.a', null, null, false, false, null],
            ],
        ];
    }
}
