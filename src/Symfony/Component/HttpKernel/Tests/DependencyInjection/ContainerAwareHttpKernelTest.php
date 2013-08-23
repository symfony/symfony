<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ContainerAwareHttpKernel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ContainerAwareHttpKernelTest extends \PHPUnit_Framework_TestCase
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
            ->expects($this->at(0))
            ->method('hasScope')
            ->with($this->equalTo('request'))
            ->will($this->returnValue(false));
        $container
            ->expects($this->at(1))
            ->method('addScope')
            ->with($this->isInstanceOf('Symfony\Component\DependencyInjection\Scope'));
        // enterScope()
        $container
            ->expects($this->at(3))
            ->method('set')
            ->with($this->equalTo('request'), $this->equalTo($request), $this->equalTo('request'))
        ;
        $container
            ->expects($this->at(4))
            ->method('set')
            ->with($this->equalTo('request'), $this->equalTo(null), $this->equalTo('request'))
        ;

        $dispatcher = new EventDispatcher();
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $kernel = new ContainerAwareHttpKernel($dispatcher, $container, $resolver);

        $controller = function () use ($expected) {
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
            ->expects($this->at(0))
            ->method('hasScope')
            ->with($this->equalTo('request'))
            ->will($this->returnValue(true));
        // enterScope()
        $container
            ->expects($this->at(2))
            ->method('set')
            ->with($this->equalTo('request'), $this->equalTo($request), $this->equalTo('request'))
        ;
        $container
            ->expects($this->at(3))
            ->method('set')
            ->with($this->equalTo('request'), $this->equalTo(null), $this->equalTo('request'))
        ;

        $dispatcher = new EventDispatcher();
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $kernel = new ContainerAwareHttpKernel($dispatcher, $container, $resolver);

        $controller = function () use ($expected) {
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
        } catch (\PHPUnit_Framework_Exception $exception) {
            throw $exception;
        } catch (\Exception $actual) {
            $this->assertSame($expected, $actual, '->handle() throws the controller exception');
        }
    }

    public function getProviderTypes()
    {
        return array(
            array(HttpKernelInterface::MASTER_REQUEST),
            array(HttpKernelInterface::SUB_REQUEST),
        );
    }
}
