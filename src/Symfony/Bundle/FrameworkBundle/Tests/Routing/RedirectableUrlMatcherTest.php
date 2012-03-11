<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Bundle\FrameworkBundle\Routing\RedirectableUrlMatcher;
use Symfony\Component\Routing\RequestContext;

class RedirectableUrlMatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testRedirectWhenNoSlash()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $matcher = new RedirectableUrlMatcher($coll, $context = new RequestContext());

        $this->assertEquals(array(
                '_controller' => 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction',
                'path'        => '/foo/',
                'permanent'   => true,
                'scheme'      => null,
                'httpPort'    => $context->getHttpPort(),
                'httpsPort'   => $context->getHttpsPort(),
                '_route'      => null,
            ),
            $matcher->match('/foo')
        );
    }

    public function testSchemeRedirect()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array('_scheme' => 'https')));

        $matcher = new RedirectableUrlMatcher($coll, $context = new RequestContext());

        $this->assertEquals(array(
                '_route' => 'foo',
            ),
            $matcher->match('/foo')
        );
    }
}
