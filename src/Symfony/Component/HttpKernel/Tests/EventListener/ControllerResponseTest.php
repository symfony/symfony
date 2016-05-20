<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ControllerResponseTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;
    
    private $kernel;
    
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }
        
        $this->dispatcher = new EventDispatcher();
        $listener = new ControllerResponseInjectorListener(array(
            'bar' => 'bar',
        ));
        $this->dispatcher->addListener(KernelEvents::VIEW, array($listener, 'onKernelView'));
        
        $this->kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        
    }
    
    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->kernel = null;
    }
    
    public function testControllerResponseIsChainable()
    {
        
        $controllerResponse = array('foo' => 'foo');
        
        $event = new GetResponseForControllerResultEvent($this->kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $controllerResponse);
        $this->dispatcher->dispatch(KernelEvents::VIEW, $event);
        
        $this->assertEquals(array('foo' => 'foo', 'bar' => 'bar'), $event->getControllerResult());
    }
}
