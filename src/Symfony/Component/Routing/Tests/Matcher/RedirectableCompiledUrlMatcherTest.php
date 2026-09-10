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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Controller\RedirectController;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Matcher\RedirectableCompiledUrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RedirectableCompiledUrlMatcherTest extends TestCase
{
    public function testRedirectWhenNoSlash()
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/foo/'));

        $matcher = $this->getMatcher($routes, $context = new RequestContext());

        $this->assertEquals(
            [
                '_controller' => RedirectController::class.'::urlRedirectAction',
                'path' => '/foo/',
                'permanent' => true,
                'scheme' => null,
                'httpPort' => $context->getHttpPort(),
                'httpsPort' => $context->getHttpsPort(),
                '_route' => 'foo',
                '_route_mapping' => [],
            ],
            $matcher->match('/foo')
        );
    }

    public function testSchemeRedirect()
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/foo', [], [], [], '', ['https']));

        $matcher = $this->getMatcher($routes, $context = new RequestContext());

        $this->assertEquals(
            [
                '_controller' => RedirectController::class.'::urlRedirectAction',
                'path' => '/foo',
                'permanent' => true,
                'scheme' => 'https',
                'httpPort' => $context->getHttpPort(),
                'httpsPort' => $context->getHttpsPort(),
                '_route' => 'foo',
                '_route_mapping' => [],
            ],
            $matcher->match('/foo')
        );
    }

    private function getMatcher(RouteCollection $routes, RequestContext $context)
    {
        $dumper = new CompiledUrlMatcherDumper($routes);

        return new RedirectableCompiledUrlMatcher($dumper->getCompiledRoutes(), $context);
    }
}
