<?php

namespace Symfony\Component\Routing\Tests\Matcher\Strategy;

use Symfony\Component\Routing\Matcher\Strategy\MatcherStrategy;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

class AlwaysMatchStrategy implements MatcherStrategy
{
    /**
     * @param string $pathinfo
     * @param Route $route
     * @param RequestContext $context
     * @return bool
     */
    public function matches($pathinfo, Route $route, RequestContext $context)
    {
        return true;
    }
}
