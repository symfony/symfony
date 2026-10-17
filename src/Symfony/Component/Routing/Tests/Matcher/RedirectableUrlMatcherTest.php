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

use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RedirectableUrlMatcherTest extends UrlMatcherTest
{
    /**
     * @dataProvider provideExplicitTrailingSlashRoutes
     */
    public function testExplicitTrailingSlashRoutes(string $firstPath, string $secondPath, string $path, string $expectedRoute, array $secondMethods = [], string $requestMethod = 'GET')
    {
        $routes = new RouteCollection();
        $routes->add('first', new Route($firstPath));
        $routes->add('second', new Route($secondPath, [], [], [], '', [], $secondMethods));

        $matcher = $this->getUrlMatcher($routes, new RequestContext('', $requestMethod), true);
        $matcher->expects($this->never())->method('redirect');

        $this->assertSame($expectedRoute, $matcher->match($path)['_route']);
    }

    public static function provideExplicitTrailingSlashRoutes(): iterable
    {
        yield ['/test', '/test/', '/test/', 'second'];
        yield ['/test/', '/test', '/test', 'second'];
        yield ['/test/{id}', '/test/{id}/', '/test/123/', 'second'];
        yield ['/test/{id}/', '/test/{id}', '/test/123', 'second'];
        yield ['/test', '/test/', '/test/', 'second', ['HEAD'], 'HEAD'];
        yield ['/test/', '/test', '/test', 'second', ['HEAD'], 'HEAD'];
        yield ['/test/{id}', '/test/{id}/', '/test/123/', 'second', ['HEAD'], 'HEAD'];
        yield ['/test/{id}/', '/test/{id}', '/test/123', 'second', ['HEAD'], 'HEAD'];
    }

    /**
     * @dataProvider provideRepeatedTrailingSlashes
     */
    public function testRepeatedTrailingSlashes(string $routePath, string $requestPath, bool $withSlashRoute, string $method, string $condition = 'false')
    {
        $routes = new RouteCollection();
        $routes->add('first', new Route($routePath));
        if ($withSlashRoute) {
            $routes->add('rejected', new Route($routePath.'/', [], [], [], '', [], [], $condition));
        }
        $routes->add('repeated', new Route($routePath.'//'));

        $matcher = $this->getUrlMatcher($routes, new RequestContext('', $method), true);
        $matcher->expects($this->once())->method('redirect')->with(rtrim($requestPath, '/'), 'first')->willReturn([]);

        $this->assertSame('first', $matcher->match($requestPath)['_route']);
    }

    public static function provideRepeatedTrailingSlashes(): iterable
    {
        foreach (['GET', 'HEAD'] as $method) {
            foreach ([false, true] as $withSlashRoute) {
                yield ['/test', '/test/', $withSlashRoute, $method];
                yield ['/test/{id}', '/test/123/', $withSlashRoute, $method];
            }
            yield ['/test', '/test///', true, $method, ''];
            yield ['/test/{id}', '/test/123///', true, $method, ''];
        }
    }

    public function testExplicitTrailingSlashWithOverlappingHosts()
    {
        $routes = new RouteCollection();
        $routes->add('first', new Route('/test/{id}', [], [], [], '{subdomain}.example.com'));
        $routes->add('second', new Route('/test/{id}/', [], [], [], 'www.example.com'));

        $matcher = $this->getUrlMatcher($routes, new RequestContext('', 'GET', 'www.example.com'), true);
        $matcher->expects($this->never())->method('redirect');

        $this->assertSame('second', $matcher->match('/test/123/')['_route']);
    }

    public function testMissingTrailingSlash()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher->expects($this->once())->method('redirect')->willReturn([]);
        $matcher->match('/foo');
    }

    public function testExtraTrailingSlash()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo'));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher->expects($this->once())->method('redirect')->willReturn([]);
        $matcher->match('/foo/');
    }

    public function testRedirectWhenNoSlashForNonSafeMethod()
    {
        $this->expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $context = new RequestContext();
        $context->setMethod('POST');
        $matcher = $this->getUrlMatcher($coll, $context);
        $matcher->match('/foo');
    }

    public function testSchemeRedirectRedirectsToFirstScheme()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', ['FTP', 'HTTPS']));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher
            ->expects($this->once())
            ->method('redirect')
            ->with('/foo', 'foo', 'ftp')
            ->willReturn(['_route' => 'foo'])
        ;
        $matcher->match('/foo');
    }

    public function testNoSchemaRedirectIfOneOfMultipleSchemesMatches()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', ['https', 'http']));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher
            ->expects($this->never())
            ->method('redirect');
        $matcher->match('/foo');
    }

    public function testSchemeRedirectWithParams()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{bar}', [], [], [], '', ['https']));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher
            ->expects($this->once())
            ->method('redirect')
            ->with('/foo/baz', 'foo', 'https')
            ->willReturn(['redirect' => 'value'])
        ;
        $this->assertEquals(['_route' => 'foo', 'bar' => 'baz', 'redirect' => 'value'], $matcher->match('/foo/baz'));
    }

    public function testSchemeRedirectForRoot()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/', [], [], [], '', ['https']));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher
            ->expects($this->once())
            ->method('redirect')
            ->with('/', 'foo', 'https')
            ->willReturn(['redirect' => 'value']);
        $this->assertEquals(['_route' => 'foo', 'redirect' => 'value'], $matcher->match('/'));
    }

    public function testSlashRedirectWithParams()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{bar}/'));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher
            ->expects($this->once())
            ->method('redirect')
            ->with('/foo/baz/', 'foo', null)
            ->willReturn(['redirect' => 'value'])
        ;
        $this->assertEquals(['_route' => 'foo', 'bar' => 'baz', 'redirect' => 'value'], $matcher->match('/foo/baz'));
    }

    public function testRedirectPreservesUrlEncoding()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo:bar/'));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher->expects($this->once())->method('redirect')->with('/foo%3Abar/')->willReturn([]);
        $matcher->match('/foo%3Abar');
    }

    public function testSchemeRequirement()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', ['https']));
        $matcher = $this->getUrlMatcher($coll, new RequestContext(), true);
        $matcher->expects($this->once())->method('redirect')->with('/foo', 'foo', 'https')->willReturn([]);
        $this->assertSame(['_route' => 'foo'], $matcher->match('/foo'));
    }

    public function testFallbackPage()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));
        $coll->add('bar', new Route('/{name}'));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher->expects($this->once())->method('redirect')->with('/foo/', 'foo')->willReturn(['_route' => 'foo']);
        $this->assertSame(['_route' => 'foo'], $matcher->match('/foo'));

        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo'));
        $coll->add('bar', new Route('/{name}/'));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher->expects($this->once())->method('redirect')->with('/foo', 'foo')->willReturn(['_route' => 'foo']);
        $this->assertSame(['_route' => 'foo'], $matcher->match('/foo/'));
    }

    public function testMissingTrailingSlashAndScheme()
    {
        $coll = new RouteCollection();
        $coll->add('foo', (new Route('/foo/'))->setSchemes(['https']));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher->expects($this->once())->method('redirect')->with('/foo/', 'foo', 'https')->willReturn([]);
        $matcher->match('/foo');
    }

    public function testSlashAndVerbPrecedenceWithRedirection()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/api/customers/{customerId}/contactpersons', [], [], [], '', [], ['post']));
        $coll->add('b', new Route('/api/customers/{customerId}/contactpersons/', [], [], [], '', [], ['get']));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $expected = [
            '_route' => 'b',
            'customerId' => '123',
        ];
        $this->assertEquals($expected, $matcher->match('/api/customers/123/contactpersons/'));

        $matcher->expects($this->once())->method('redirect')->with('/api/customers/123/contactpersons/')->willReturn([]);
        $this->assertEquals($expected, $matcher->match('/api/customers/123/contactpersons'));
    }

    public function testNonGreedyTrailingRequirement()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{a}', [], ['a' => '\d+']));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher->expects($this->once())->method('redirect')->with('/123')->willReturn([]);

        $this->assertEquals(['_route' => 'a', 'a' => '123'], $matcher->match('/123/'));
    }

    public function testTrailingRequirementWithDefaultA()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/fr-fr/{a}', ['a' => 'aaa'], ['a' => '.+']));

        $matcher = $this->getUrlMatcher($coll, null, true);
        $matcher->expects($this->once())->method('redirect')->with('/fr-fr')->willReturn([]);

        $this->assertEquals(['_route' => 'a', 'a' => 'aaa'], $matcher->match('/fr-fr/'));
    }

    protected function getUrlMatcher(RouteCollection $routes, ?RequestContext $context = null, bool $mock = false)
    {
        if (!$mock) {
            return new class($routes, $context ?? new RequestContext()) extends RedirectableUrlMatcher {
                public function redirect(string $path, string $route, ?string $scheme = null): array
                {
                    return [];
                }
            };
        }

        return $this->getMockBuilder(RedirectableUrlMatcher::class)
            ->setConstructorArgs([$routes, $context ?? new RequestContext()])
            ->onlyMethods(['redirect'])
            ->getMock();
    }
}
