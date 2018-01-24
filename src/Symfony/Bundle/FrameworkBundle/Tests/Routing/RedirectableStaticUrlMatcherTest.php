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
use Symfony\Component\Routing\Matcher\Dumper\StaticUrlMatcherDumper;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Bundle\FrameworkBundle\Routing\RedirectableStaticUrlMatcher;
use Symfony\Component\Routing\RequestContext;

/**
 * @requires function \Symfony\Component\Routing\Matcher\StaticUrlMatcher::match
 */
class RedirectableStaticUrlMatcherTest extends TestCase
{
    public function testRedirectWhenNoSlash()
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/foo/'));

        $matcher = $this->getMatcher($routes, $context = new RequestContext());

        $this->assertEquals(array(
                '_controller' => 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction',
                'path' => '/foo/',
                'permanent' => true,
                'scheme' => null,
                'httpPort' => $context->getHttpPort(),
                'httpsPort' => $context->getHttpsPort(),
                '_route' => 'foo',
            ),
            $matcher->match('/foo')
        );
    }

    public function testSchemeRedirect()
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/foo', array(), array(), array(), '', array('https')));

        $matcher = $this->getMatcher($routes, $context = new RequestContext());

        $this->assertEquals(array(
                '_controller' => 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction',
                'path' => '/foo',
                'permanent' => true,
                'scheme' => 'https',
                'httpPort' => $context->getHttpPort(),
                'httpsPort' => $context->getHttpsPort(),
                '_route' => 'foo',
            ),
            $matcher->match('/foo')
        );
    }

    private function getMatcher(RouteCollection $routes, RequestContext $context)
    {
        $dumper = new StaticUrlMatcherDumper($routes);
        $path = sys_get_temp_dir().'/php_matcher.'.uniqid('StaticUrlMatcher').'.php';

        file_put_contents($path, $dumper->dump());
        $matcher = new RedirectableStaticUrlMatcher(require $path, $context);
        unlink($path);

        return $matcher;
    }
}
