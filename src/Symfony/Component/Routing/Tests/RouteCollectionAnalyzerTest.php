<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouteCollectionAnalyzer;

class RouteCollectionAnalyzerTest extends TestCase
{
    public function testNoIssuesForDistinctRoutes()
    {
        $routes = $this->createCollection([
            'home' => new Route('/'),
            'blog' => new Route('/blog'),
            'blog_show' => new Route('/blog/{id}', requirements: ['id' => '\d+']),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testEmptyCollection()
    {
        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze(new RouteCollection()));
    }

    public function testDuplicateRoute()
    {
        $routes = $this->createCollection([
            'first' => new Route('/foo'),
            'second' => new Route('/foo'),
        ]);

        $this->assertSame([[
            'type' => RouteCollectionAnalyzer::DUPLICATE,
            'severity' => 'error',
            'route' => 'second',
            'path' => '/foo',
            'previous_route' => 'first',
            'previous_path' => '/foo',
            'message' => 'Route "second" (/foo) has the same path, host, methods, schemes and condition as route "first" (/foo) declared before it and can never match.',
        ]], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testDuplicateIgnoresMethodsOrder()
    {
        $routes = $this->createCollection([
            'first' => new Route('/foo', methods: ['GET', 'POST']),
            'second' => new Route('/foo', methods: ['POST', 'GET']),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertSame(RouteCollectionAnalyzer::DUPLICATE, $issues[0]['type']);
    }

    public function testSameDefinitionWithDifferentConditionsIsNotDuplicate()
    {
        $routes = $this->createCollection([
            'first' => new Route('/foo', condition: 'request.isXmlHttpRequest()'),
            'second' => new Route('/foo'),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testShadowedRoute()
    {
        $routes = $this->createCollection([
            'blog_show' => new Route('/blog/{slug}'),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $this->assertSame([[
            'type' => RouteCollectionAnalyzer::SHADOWED,
            'severity' => 'error',
            'route' => 'blog_latest',
            'path' => '/blog/latest',
            'previous_route' => 'blog_show',
            'previous_path' => '/blog/{slug}',
            'message' => 'Route "blog_latest" (/blog/latest) can never match because route "blog_show" (/blog/{slug}) declared before it matches all its URLs.',
        ]], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testRouteDeclaredBeforeTheGenericOneIsNotReported()
    {
        $routes = $this->createCollection([
            'blog_latest' => new Route('/blog/latest'),
            'blog_show' => new Route('/blog/{slug}'),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testShadowedRouteWithPlaceholders()
    {
        $routes = $this->createCollection([
            'catch_all' => new Route('/{a}/{b}'),
            'post' => new Route('/post/{id}', requirements: ['id' => '\d+']),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertSame(RouteCollectionAnalyzer::SHADOWED, $issues[0]['type']);
        $this->assertSame('post', $issues[0]['route']);
        $this->assertSame('catch_all', $issues[0]['previous_route']);
    }

    public function testRequirementPreventsShadowing()
    {
        $routes = $this->createCollection([
            'blog_show' => new Route('/blog/{slug}', requirements: ['slug' => '\d+']),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testPartialOverlapOfRequirementsIsNotShadowed()
    {
        $routes = $this->createCollection([
            'numbers' => new Route('/item/{id}', requirements: ['id' => '\d+']),
            'any' => new Route('/item/{id}', requirements: ['id' => '[a-z0-9]+']),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testAmbiguousRouteWhenPreviousRouteIsRestrictedToSomeMethods()
    {
        $routes = $this->createCollection([
            'blog_show' => new Route('/blog/{slug}', methods: ['GET']),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $this->assertSame([[
            'type' => RouteCollectionAnalyzer::AMBIGUOUS,
            'severity' => 'warning',
            'route' => 'blog_latest',
            'path' => '/blog/latest',
            'previous_route' => 'blog_show',
            'previous_path' => '/blog/{slug}',
            'message' => 'Route "blog_latest" (/blog/latest) overlaps route "blog_show" (/blog/{slug}) declared before it, which has placeholders without requirements and matches some of its requests.',
        ]], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testPartialOverlapWithRequirementsIsNotAmbiguous()
    {
        $routes = $this->createCollection([
            'blog_show' => new Route('/blog/{slug}', requirements: ['slug' => '[a-z]+'], methods: ['GET']),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testPreviousRouteWithConditionDoesNotShadow()
    {
        $routes = $this->createCollection([
            'blog_show' => new Route('/blog/{slug}', requirements: ['slug' => '[a-z]+'], condition: 'request.isXmlHttpRequest()'),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testPreviousRouteWithConditionAndUnrestrictedPlaceholderIsAmbiguous()
    {
        $routes = $this->createCollection([
            'blog_show' => new Route('/blog/{slug}', condition: 'request.isXmlHttpRequest()'),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertSame(RouteCollectionAnalyzer::AMBIGUOUS, $issues[0]['type']);
    }

    public function testDisjointMethodsDoNotOverlap()
    {
        $routes = $this->createCollection([
            'blog_show' => new Route('/blog/{slug}', methods: ['GET']),
            'blog_latest' => new Route('/blog/latest', methods: ['POST']),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testShadowedWhenMethodsAreASubset()
    {
        $routes = $this->createCollection([
            'blog_show' => new Route('/blog/{slug}', methods: ['GET', 'POST']),
            'blog_latest' => new Route('/blog/latest', methods: ['POST']),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertSame(RouteCollectionAnalyzer::SHADOWED, $issues[0]['type']);
    }

    public function testGetRouteShadowsHeadRoute()
    {
        $routes = $this->createCollection([
            'get' => new Route('/foo', methods: ['GET']),
            'head' => new Route('/foo', methods: ['HEAD']),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertSame(RouteCollectionAnalyzer::SHADOWED, $issues[0]['type']);
        $this->assertSame('head', $issues[0]['route']);
    }

    public function testRouteWithoutSchemesShadowsRouteWithSchemes()
    {
        $routes = $this->createCollection([
            'any' => new Route('/foo'),
            'secure' => new Route('/foo', schemes: ['https']),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertSame(RouteCollectionAnalyzer::SHADOWED, $issues[0]['type']);
        $this->assertSame('secure', $issues[0]['route']);
    }

    public function testRouteWithSchemesDoesNotShadowRouteWithoutSchemes()
    {
        $routes = $this->createCollection([
            'secure' => new Route('/foo', schemes: ['https']),
            'any' => new Route('/foo'),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testDisjointSchemesDoNotOverlap()
    {
        $routes = $this->createCollection([
            'secure' => new Route('/foo', schemes: ['https']),
            'insecure' => new Route('/foo', schemes: ['http']),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testDifferentHostsDoNotOverlap()
    {
        $routes = $this->createCollection([
            'a' => new Route('/foo', host: 'a.example.com'),
            'b' => new Route('/foo', host: 'b.example.com'),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testRouteWithHostDoesNotShadowRouteWithoutHost()
    {
        $routes = $this->createCollection([
            'with_host' => new Route('/foo', host: 'example.com'),
            'without_host' => new Route('/foo'),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testRouteWithoutHostShadowsRouteWithHost()
    {
        $routes = $this->createCollection([
            'without_host' => new Route('/foo'),
            'with_host' => new Route('/foo', host: 'example.com'),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertSame(RouteCollectionAnalyzer::SHADOWED, $issues[0]['type']);
    }

    public function testRouteWithUnsatisfiableSamplesIsNotReportedAsShadowed()
    {
        $routes = $this->createCollection([
            'catch_all' => new Route('/x/{value}'),
            'special' => new Route('/x/{value}', requirements: ['value' => 'zzz-unknown']),
        ]);

        $this->assertSame([], (new RouteCollectionAnalyzer())->analyze($routes));
    }

    public function testDefaultValueIsUsedAsSample()
    {
        $routes = $this->createCollection([
            'catch_all' => new Route('/x/{value}'),
            'special' => new Route('/x/{value}', ['value' => 'zzz-unknown'], ['value' => 'zzz-unknown']),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertSame(RouteCollectionAnalyzer::SHADOWED, $issues[0]['type']);
        $this->assertSame('special', $issues[0]['route']);
    }

    public function testShadowedIsReportedOverAmbiguous()
    {
        $routes = $this->createCollection([
            'post_only' => new Route('/blog/{slug}', methods: ['POST']),
            'generic' => new Route('/blog/{name}'),
            'latest' => new Route('/blog/latest'),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(2, $issues);

        $this->assertSame('generic', $issues[0]['route']);
        $this->assertSame(RouteCollectionAnalyzer::AMBIGUOUS, $issues[0]['type']);
        $this->assertSame('post_only', $issues[0]['previous_route']);

        $this->assertSame('latest', $issues[1]['route']);
        $this->assertSame(RouteCollectionAnalyzer::SHADOWED, $issues[1]['type']);
        $this->assertSame('generic', $issues[1]['previous_route']);
    }

    public function testOnlyOneIssueIsReportedPerRoute()
    {
        $routes = $this->createCollection([
            'first' => new Route('/foo'),
            'second' => new Route('/foo'),
            'third' => new Route('/foo'),
        ]);

        $issues = (new RouteCollectionAnalyzer())->analyze($routes);

        $this->assertCount(2, $issues);
        $this->assertSame(['second', 'first'], [$issues[0]['route'], $issues[0]['previous_route']]);
        $this->assertSame(['third', 'first'], [$issues[1]['route'], $issues[1]['previous_route']]);
    }

    /**
     * @param array<string, Route> $routes
     */
    private function createCollection(array $routes): RouteCollection
    {
        $collection = new RouteCollection();
        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        return $collection;
    }
}
