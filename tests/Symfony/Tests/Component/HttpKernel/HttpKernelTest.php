<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel;

use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;

class HttpKernelTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleGetsTheRequestFromTheContainer()
    {
        $request = Request::create('/');
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->any())
                  ->method('get')
                  ->will($this->returnValue($request))
        ;

        $kernel = new HttpKernel($container, new EventDispatcher(), $this->getResolver());

        $kernel->handle();

        $this->assertEquals($request, $kernel->getRequest());
    }

    public function testHandleSetsTheRequestIfPassed()
    {
        $request = Request::create('/');
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->exactly(2))
                  ->method('set')
                  ->with('request', $request)
        ;

        $kernel = new HttpKernel($container, new EventDispatcher(), $this->getResolver());

        $kernel->handle($request);
    }

    protected function getResolver($controller = null)
    {
        if (null === $controller) {
            $controller = function () { return new Response('Hello'); };
        }
        $resolver = $this->getMock('Symfony\Component\HttpKernel\Controller\ControllerResolverInterface');
        $resolver->expects($this->any())
                 ->method('getController')
                 ->will($this->returnValue($controller))
        ;
        $resolver->expects($this->any())
                 ->method('getArguments')
                 ->will($this->returnValue(array()))
        ;

        return $resolver;
    }
}
