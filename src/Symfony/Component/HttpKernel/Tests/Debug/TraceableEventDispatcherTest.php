<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableEventDispatcherTest extends TestCase
{
    public function testStopwatchSections()
    {
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch = new Stopwatch());
        $kernel = $this->getHttpKernel($dispatcher, function () { return new Response(); });
        $request = Request::create('/');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $events = $stopwatch->getSectionEvents($response->headers->get('X-Debug-Token'));
        $this->assertEquals(array(
            '__section__',
            'kernel.request',
            'kernel.controller',
            'kernel.controller_arguments',
            'controller',
            'kernel.response',
            'kernel.terminate',
        ), array_keys($events));
    }

    public function testStopwatchCheckControllerOnRequestEvent()
    {
        $stopwatch = $this->getMockBuilder('Symfony\Component\Stopwatch\Stopwatch')
            ->setMethods(array('isStarted'))
            ->getMock();
        $stopwatch->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));

        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch);

        $kernel = $this->getHttpKernel($dispatcher, function () { return new Response(); });
        $request = Request::create('/');
        $kernel->handle($request);
    }

    public function testStopwatchStopControllerOnRequestEvent()
    {
        $stopwatch = $this->getMockBuilder('Symfony\Component\Stopwatch\Stopwatch')
            ->setMethods(array('isStarted', 'stop', 'stopSection'))
            ->getMock();
        $stopwatch->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(true));
        $stopwatch->expects($this->once())
            ->method('stop');
        $stopwatch->expects($this->once())
            ->method('stopSection');

        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch);

        $kernel = $this->getHttpKernel($dispatcher, function () { return new Response(); });
        $request = Request::create('/');
        $kernel->handle($request);
    }

    public function testAddListenerNested()
    {
        $called1 = false;
        $called2 = false;
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $dispatcher->addListener('my-event', function () use ($dispatcher, &$called1, &$called2) {
            $called1 = true;
            $dispatcher->addListener('my-event', function () use (&$called2) {
                $called2 = true;
            });
        });
        $dispatcher->dispatch('my-event');
        $this->assertTrue($called1);
        $this->assertFalse($called2);
        $dispatcher->dispatch('my-event');
        $this->assertTrue($called2);
    }

    public function testListenerCanRemoveItselfWhenExecuted()
    {
        $eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $listener1 = function () use ($eventDispatcher, &$listener1) {
            $eventDispatcher->removeListener('foo', $listener1);
        };
        $eventDispatcher->addListener('foo', $listener1);
        $eventDispatcher->addListener('foo', function () {});
        $eventDispatcher->dispatch('foo');

        $this->assertCount(1, $eventDispatcher->getListeners('foo'), 'expected listener1 to be removed');
    }

    protected function getHttpKernel($dispatcher, $controller)
    {
        $controllerResolver = $this->getMockBuilder('Symfony\Component\HttpKernel\Controller\ControllerResolverInterface')->getMock();
        $controllerResolver->expects($this->once())->method('getController')->will($this->returnValue($controller));
        $argumentResolver = $this->getMockBuilder('Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface')->getMock();
        $argumentResolver->expects($this->once())->method('getArguments')->will($this->returnValue(array()));

        return new HttpKernel($dispatcher, $controllerResolver, new RequestStack(), $argumentResolver);
    }
}
