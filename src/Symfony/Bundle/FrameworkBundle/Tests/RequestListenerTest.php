<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\RequestContext;
use Symfony\Bundle\FrameworkBundle\RequestListener;

class RequestListenerTest extends \PHPUnit_Framework_TestCase
{
    private $container;
    private $router;
    
    protected function setUp()
    {
        $this->container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        // TODO: Change to Symfony\Component\Routing\RouterInterface once has setContext method
        $this->router = $this->getMockBuilder('Symfony\Component\Routing\Router')
                             ->disableOriginalConstructor()
                             ->getMock();
    }
    
    public function testConstructPortGetsPassedInRouterSetContext()
    {
        $listener = new RequestListener($this->container, $this->router, 99);
        
        $expectedContext = new RequestContext();
        $expectedContext->setHttpPort(99);
        $this->router->expects($this->once())
                     ->method('setContext')
                     ->with($expectedContext);
        
        $event = $this->createGetResponseEventForUri('http://localhost:99/');
        $listener->onCoreRequest($event);
    }
    
    public function testRequestPortGetsPassedInRouterSetContextIfNoConstructorPort()
    {
        $listener = new RequestListener($this->container, $this->router);
        
        $expectedContext = new RequestContext();
        $expectedContext->setHttpPort(99);
        $this->router->expects($this->once())
                     ->method('setContext')
                     ->with($expectedContext);
        
        $event = $this->createGetResponseEventForUri('http://localhost:99/');
        $listener->onCoreRequest($event);
    }
    
    /**
     * @param string $uri
     * @return GetResponseEvent 
     */
    private function createGetResponseEventForUri($uri)
    {
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $request = Request::create($uri);
        $request->attributes->set('_controller', null); // Prevents going in to routing process
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
        
        return $event;
    }
}