<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Matcher;

use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class CompiledRedirectableUrlMatcherTest extends RedirectableUrlMatcherTest
{
    protected function getUrlMatcher(RouteCollection $routes, RequestContext $context = null)
    {
        $dumper = new CompiledUrlMatcherDumper($routes);
        $compiledRoutes = $dumper->getCompiledRoutes();

        return $this->getMockBuilder(TestCompiledRedirectableUrlMatcher::class)
            ->setConstructorArgs([$compiledRoutes, $context ?: new RequestContext()])
            ->setMethods(['redirect'])
            ->getMock();
    }
}

class TestCompiledRedirectableUrlMatcher extends CompiledUrlMatcher implements RedirectableUrlMatcherInterface
{
    public function redirect(string $path, string $route, string $scheme = null): array
    {
        return [];
    }
}
