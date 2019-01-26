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
        $this->staticRoutes = [
            '/rootprefix/test' => [[['_route' => 'static'], null, null, null, false, false, null]],
            '/with-condition' => [[['_route' => 'with-condition'], null, null, null, false, false, -1]],
        ];
        $this->regexpList = [
            0 => '{^(?'
                    .'|/rootprefix/([^/]++)(*:27)'
                .')/?$}sD',
        ];
        $this->dynamicRoutes = [
            27 => [
                [['_route' => 'dynamic'], ['var'], null, null, false, true, null],
                [null, null, null, null, false, false, 0],
            ],
        ];
        $this->checkCondition = static function ($condition, $context, $request) {
            switch ($condition) {
                case -1: return ($context->getMethod() == "GET");
            }
        };
    }
}
