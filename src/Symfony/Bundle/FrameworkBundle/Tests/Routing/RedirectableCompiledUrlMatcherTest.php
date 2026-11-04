<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\RedirectableCompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @requires function \Symfony\Component\Routing\Matcher\CompiledUrlMatcher::match
 */
class RedirectableCompiledUrlMatcherTest extends TestCase
{
    public function testRedirectWhenNoSlash()
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/foo/'));

        $matcher = $this->getMatcher($routes, $context = new RequestContext());

        $this->assertEquals([
                '_controller' => 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction',
                'path' => '/foo/',
                'permanent' => true,
                'scheme' => null,
                'httpPort' => $context->getHttpPort(),
                'httpsPort' => $context->getHttpsPort(),
                '_route' => 'foo',
            ],
            $matcher->match('/foo')
        );
    }

    public function testSchemeRedirect()
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/foo', [], [], [], '', ['https']));

        $matcher = $this->getMatcher($routes, $context = new RequestContext());

        $this->assertEquals([
                '_controller' => 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction',
                'path' => '/foo',
                'permanent' => true,
                'scheme' => 'https',
                'httpPort' => $context->getHttpPort(),
                'httpsPort' => $context->getHttpsPort(),
                '_route' => 'foo',
            ],
            $matcher->match('/foo')
        );
    }

    /**
     * @dataProvider provideLeadingDoubleSlash
     */
    public function testTrailingSlashRedirectEncodesLeadingDoubleSlash(string $pathinfo, string $redirectPath, string $slug)
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/{slug}/edit', [], ['slug' => '.+']));

        $matcher = $this->getMatcher($routes, new RequestContext());

        $this->assertSame($redirectPath, $matcher->match($pathinfo.'/')['path']);
        $this->assertSame(['_route' => 'foo', 'slug' => $slug], $matcher->match($redirectPath));
    }

    /**
     * @dataProvider provideLeadingDoubleSlash
     */
    public function testSchemeRedirectEncodesLeadingDoubleSlash(string $pathinfo, string $redirectPath, string $slug)
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/{slug}/edit', [], ['slug' => '.+'], [], '', ['https']));

        $matcher = $this->getMatcher($routes, new RequestContext());

        $parameters = $matcher->match($pathinfo);
        $this->assertSame($redirectPath, $parameters['path']);
        $this->assertSame('https', $parameters['scheme']);

        $matcher = $this->getMatcher($routes, new RequestContext('', 'GET', 'localhost', 'https'));
        $this->assertSame(['_route' => 'foo', 'slug' => $slug], $matcher->match($redirectPath));
    }

    public static function provideLeadingDoubleSlash(): iterable
    {
        yield ['//evil.example/edit', '/%2Fevil.example/edit', '/evil.example'];
        yield ['///evil.example/edit', '/%2F/evil.example/edit', '//evil.example'];
        yield ['/\\evil.example/edit', '/%5Cevil.example/edit', '\\evil.example'];
        yield ["/\t/evil.example/edit", "/\t%2Fevil.example/edit", "\t/evil.example"];
    }

    private function getMatcher(RouteCollection $routes, RequestContext $context)
    {
        $dumper = new CompiledUrlMatcherDumper($routes);

        return new RedirectableCompiledUrlMatcher($dumper->getCompiledRoutes(), $context);
    }
}
