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
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Bundle\FrameworkBundle\Tests\Logger;
use Symfony\Bundle\FrameworkBundle\Tests\Kernel;

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

        $returnResponse = $controller->redirectAction('');

        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $returnResponse);

        $this->assertEquals(410, $returnResponse->getStatusCode());
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

        $request->attributes = new ParameterBag(array('route' => $route, '_route' => 'current-route', 'permanent' => $permanent) + $params);

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

        $this->assertTrue($returnResponse->isRedirect());
        $this->assertTrue($returnResponse->isRedirected($url));
        $this->assertEquals($expectedCode, $returnResponse->getStatusCode());
    }

    public function provider()
    {
        return array(
            array(true, 301),
            array(false, 302),
        );
    }
}
