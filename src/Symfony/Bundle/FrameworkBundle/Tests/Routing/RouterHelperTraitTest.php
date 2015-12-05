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

use Symfony\Bundle\FrameworkBundle\Routing\RouterHelperTrait;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RouterHelperTraitTest extends TestCase
{
    public function testGenerateUrl()
    {
        $router = $this->getMock(UrlGeneratorInterface::class);
        $router->expects($this->once())->method('generate')->willReturn('/foo');

        $helper = new DummyRouterHelper($router);

        $this->assertEquals('/foo', $helper->generateUrl('foo'));
    }

    public function testGenerateUrlWithContainer()
    {
        $router = $this->getMock(UrlGeneratorInterface::class);
        $router->expects($this->once())->method('generate')->willReturn('/foo');

        $container = new Container();
        $container->set('router', $router);

        $helper = new DummyRouterHelperWithContainer();
        $helper->setContainer($container);

        $this->assertEquals('/foo', $helper->generateUrl('foo'));
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testGenerateUrlWithMissingDependencies()
    {
        $helper = new DummyRouterHelperWithContainer();
        $helper->generateUrl('acme.my_route');
    }

    public function testRedirectToRoute()
    {
        $router = $this->getMock(UrlGeneratorInterface::class);
        $router->expects($this->once())->method('generate')->willReturn('/foo');

        $helper = new DummyRouterHelper($router);
        $response = $helper->redirectToRoute('foo');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/foo', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testRedirectToRouteWithContainer()
    {
        $router = $this->getMock(UrlGeneratorInterface::class);
        $router->expects($this->once())->method('generate')->willReturn('/foo');

        $container = new Container();
        $container->set('router', $router);

        $helper = new DummyRouterHelperWithContainer();
        $helper->setContainer($container);
        $response = $helper->redirectToRoute('foo');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/foo', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());
    }
}

class DummyRouterHelper
{
    use RouterHelperTrait {
        generateUrl as public;
        redirectToRoute as public;
    }

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }
}

class DummyRouterHelperWithContainer implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use RouterHelperTrait {
        generateUrl as public;
        redirectToRoute as public;
    }
}
