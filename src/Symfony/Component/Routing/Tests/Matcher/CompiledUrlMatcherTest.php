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
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class CompiledUrlMatcherTest extends UrlMatcherTest
{
    public function testStaticHostIsCaseInsensitive()
    {
        $collection = new RouteCollection();
        $collection->add('static_host_route', new Route('/test', [], [], [], 'API.example.com'));

        $context = new RequestContext('/test', 'GET', 'api.example.com');
        $matcher = new UrlMatcher($collection, $context);

        $result = $matcher->match('/test');
        $this->assertEquals('static_host_route', $result['_route'], 'UrlMatcher should match case-insensitive host');

        $dumper = new CompiledUrlMatcherDumper($collection);
        $compiledRoutes = $dumper->getCompiledRoutes();

        $compiledMatcher = new CompiledUrlMatcher($compiledRoutes, $context);

        $result = $compiledMatcher->match('/test');
        $this->assertEquals('static_host_route', $result['_route'], 'CompiledUrlMatcher should match case-insensitive host');
    }

    public function testCompiledRoutesDumpedByAnOlderVersionStillMatch()
    {
        // routes dumped before the port was added carry 7 elements instead of 8
        $compiledRoutes = [
            false,
            ['/foo' => [[['_route' => 'foo'], null, null, null, false, false, null]]],
            [0 => '{^(?|/bar/([^/]++)(*:20))/?$}sD'],
            [20 => [[['_route' => 'bar'], ['id'], null, null, false, true, null], [null, null, null, null, false, false, 0]]],
            null,
        ];

        $matcher = new CompiledUrlMatcher($compiledRoutes, new RequestContext());

        $this->assertSame(['_route' => 'foo'], $matcher->match('/foo'));
        $this->assertSame(['_route' => 'bar', 'id' => '1'], $matcher->match('/bar/1'));
    }

    protected function getUrlMatcher(RouteCollection $routes, ?RequestContext $context = null)
    {
        $dumper = new CompiledUrlMatcherDumper($routes);

        return new CompiledUrlMatcher($dumper->getCompiledRoutes(), $context ?? new RequestContext());
    }
}
