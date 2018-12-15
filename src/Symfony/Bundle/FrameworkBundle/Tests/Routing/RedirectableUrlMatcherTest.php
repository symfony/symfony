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
use Symfony\Bundle\FrameworkBundle\Routing\RedirectableUrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RedirectableUrlMatcherTest extends TestCase
{
    public function testRedirectWhenNoSlash()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $matcher = new RedirectableUrlMatcher($coll, $context = new RequestContext());

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
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', ['https']));

        $matcher = new RedirectableUrlMatcher($coll, $context = new RequestContext());

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
}
