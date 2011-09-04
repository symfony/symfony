<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\FrameworkBundle\HttpKernel;
use Symfony\Component\EventDispatcher\EventDispatcher;

class HttpKernelTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getProviderTypes
     */
    public function testHandle($type)
    {
        $request = new Request();
        $expected = new Response();

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->once())
            ->method('enterScope')
            ->with($this->equalTo('request'))
        ;
        $container
            ->expects($this->once())
            ->method('leaveScope')
            ->with($this->equalTo('request'))
        ;
        $container
            ->expects($this->once())
            ->method('set')
            ->with($this->equalTo('request'), $this->equalTo($request), $this->equalTo('request'))
        ;

        $dispatcher = new EventDispatcher();
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $kernel = new HttpKernel($dispatcher, $container, $resolver);

        $controller = function() use($expected)
        {
            return $expected;
        };

        $resolver->expects($this->once())
            ->method('getController')
            ->with($request)
            ->will($this->returnValue($controller));
        $resolver->expects($this->once())
            ->method('getArguments')
            ->with($request, $controller)
            ->will($this->returnValue(array()));

        $actual = $kernel->handle($request, $type);

        $this->assertSame($expected, $actual, '->handle() returns the response');
    }

    /**
     * @dataProvider getProviderTypes
     */
    public function testHandleRestoresThePreviousRequestOnException($type)
    {
        $request = new Request();
        $expected = new \Exception();

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->once())
            ->method('enterScope')
            ->with($this->equalTo('request'))
        ;
        $container
            ->expects($this->once())
            ->method('leaveScope')
            ->with($this->equalTo('request'))
        ;
        $container
            ->expects($this->once())
            ->method('set')
            ->with($this->equalTo('request'), $this->equalTo($request), $this->equalTo('request'))
        ;

        $dispatcher = new EventDispatcher();
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $kernel = new HttpKernel($dispatcher, $container, $resolver);

        $controller = function() use ($expected)
        {
            throw $expected;
        };

        $resolver->expects($this->once())
            ->method('getController')
            ->with($request)
            ->will($this->returnValue($controller));
        $resolver->expects($this->once())
            ->method('getArguments')
            ->with($request, $controller)
            ->will($this->returnValue(array()));

        try {
            $kernel->handle($request, $type);
            $this->fail('->handle() suppresses the controller exception');
        } catch (\Exception $actual) {
            $this->assertSame($expected, $actual, '->handle() throws the controller exception');
        }
    }

    public function testGenerateInternalUriHandlesNullValues()
    {
        $request = new Request();

        $router = $this->getMock('Symfony\\Component\\Routing\\RouterInterface');
        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface');
        $container
            ->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo('router'))
            ->will($this->returnValue($router))
        ;
        $container
            ->expects($this->at('1'))
            ->method('get')
            ->with($this->equalTo('request'))
            ->will($this->returnValue($request))
        ;

        $controller = 'AController';
        $attributes = array('anAttribute' => null);
        $query = array('aQueryParam' => null);

        $expectedPath = 'none';

        $routeParameters = array('controller' => $controller, 'path' => $expectedPath, '_format' => 'html');
        $router
            ->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('_internal'), $this->equalTo($routeParameters))
            ->will($this->returnValue('GENERATED_URI'))
        ;

        $dispatcher = new EventDispatcher();
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $kernel = new HttpKernel($dispatcher, $container, $resolver);

        $uri = $kernel->generateInternalUri($controller, $attributes, $query);
        $this->assertEquals('GENERATED_URI', $uri);
    }

    public function getProviderTypes()
    {
        return array(
            array(HttpKernelInterface::MASTER_REQUEST),
            array(HttpKernelInterface::SUB_REQUEST),
        );
    }
}
