<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;

/**
 * @author Marcin Sikon<marcin.sikon@gmail.com>
 */
class RedirectControllerTest extends TestCase
{
    public function testEmptyRoute()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $controller = new RedirectController();
        $controller->setContainer($container);

        $returnResponse = $controller->redirectAction('', true);
        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $returnResponse);
        $this->assertEquals(410, $returnResponse->getStatusCode());

        $returnResponse = $controller->redirectAction('', false);
        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $returnResponse);
        $this->assertEquals(404, $returnResponse->getStatusCode());
    }

    /**
     * @dataProvider provider
     */
    public function testRoute($permanent, $expectedCode)
    {
        $request = new Request();

        $route = 'new-route';
        $url = '/redirect-url';
        $params = array('additional-parameter' => 'value');
        $attributes = array(
            'route' => $route,
            'permanent' => $permanent,
            '_route' => 'current-route',
            '_route_params' => array(
                'route' => $route,
                'permanent' => $permanent,
            ),
        );
        $attributes['_route_params'] = $attributes['_route_params'] + $params;

        $request->attributes = new ParameterBag($attributes);

        $router = $this->getMock('Symfony\Component\Routing\RouterInterface');
        $router
            ->expects($this->once())
            ->method('generate')
            ->with($this->equalTo($route), $this->equalTo($params))
            ->will($this->returnValue($url));

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $container
            ->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo('request'))
            ->will($this->returnValue($request));

        $container
            ->expects($this->at(1))
            ->method('get')
            ->with($this->equalTo('router'))
            ->will($this->returnValue($router));

        $controller = new RedirectController();
        $controller->setContainer($container);

        $returnResponse = $controller->redirectAction($route, $permanent);

        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $returnResponse);

        $this->assertTrue($returnResponse->isRedirect($url));
        $this->assertEquals($expectedCode, $returnResponse->getStatusCode());
    }

    public function provider()
    {
        return array(
            array(true, 301),
            array(false, 302),
        );
    }

    public function testEmptyPath()
    {
        $controller = new RedirectController();

        $returnResponse = $controller->urlRedirectAction('', true);
        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $returnResponse);
        $this->assertEquals(410, $returnResponse->getStatusCode());

        $returnResponse = $controller->urlRedirectAction('', false);
        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $returnResponse);
        $this->assertEquals(404, $returnResponse->getStatusCode());
    }

    public function testFullURL()
    {
        $controller = new RedirectController();
        $returnResponse = $controller->urlRedirectAction('http://foo.bar/');

        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $returnResponse);

        $this->assertEquals('http://foo.bar/', $returnResponse->headers->get('Location'));
        $this->assertEquals(302, $returnResponse->getStatusCode());
    }
}
