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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\EventDispatcher\Event;

class TraceableEventDispatcherTest extends TestCase
{
    public function testStopwatchSections()
    {
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch = new Stopwatch());
        $kernel = $this->getHttpKernel($dispatcher);
        $request = Request::create('/');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $events = $stopwatch->getSectionEvents($request->attributes->get('_stopwatch_token'));
        $this->assertEquals([
            '__section__',
            'kernel.request',
            'kernel.controller',
            'kernel.controller_arguments',
            'controller',
            'kernel.response',
            'kernel.terminate',
        ], array_keys($events));
    }

    public function testStopwatchCheckControllerOnRequestEvent()
    {
        $stopwatch = $this->getMockBuilder(Stopwatch::class)
            ->onlyMethods(['isStarted'])
            ->getMock();
        $stopwatch->expects($this->once())
            ->method('isStarted')
            ->willReturn(false);

        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch);

        $kernel = $this->getHttpKernel($dispatcher);
        $request = Request::create('/');
        $kernel->handle($request);
    }

    public function testStopwatchStopControllerOnRequestEvent()
    {
        $stopwatch = $this->getMockBuilder(Stopwatch::class)
            ->onlyMethods(['isStarted', 'stop'])
            ->getMock();
        $stopwatch->expects($this->once())
            ->method('isStarted')
            ->willReturn(true);
        $stopwatch->expects($this->exactly(3))
            ->method('stop');

        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch);

        $kernel = $this->getHttpKernel($dispatcher);
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
        $dispatcher->dispatch(new Event(), 'my-event');
        $this->assertTrue($called1);
        $this->assertFalse($called2);
        $dispatcher->dispatch(new Event(), 'my-event');
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
        $eventDispatcher->dispatch(new Event(), 'foo');

        $this->assertCount(1, $eventDispatcher->getListeners('foo'), 'expected listener1 to be removed');
    }

    protected function getHttpKernel($dispatcher)
    {
        $controllerResolver = $this->createMock(ControllerResolverInterface::class);
        $controllerResolver->expects($this->once())->method('getController')->willReturn(fn () => new Response());
        $argumentResolver = $this->createMock(ArgumentResolverInterface::class);
        $argumentResolver->expects($this->once())->method('getArguments')->willReturn([]);

        return new HttpKernel($dispatcher, $controllerResolver, new RequestStack(), $argumentResolver);
    }
}
