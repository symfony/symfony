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
        self::assertEquals([
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
        $stopwatch = self::getMockBuilder(Stopwatch::class)
            ->setMethods(['isStarted'])
            ->getMock();
        $stopwatch->expects(self::once())
            ->method('isStarted')
            ->willReturn(false);

        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch);

        $kernel = $this->getHttpKernel($dispatcher);
        $request = Request::create('/');
        $kernel->handle($request);
    }

    public function testStopwatchStopControllerOnRequestEvent()
    {
        $stopwatch = self::getMockBuilder(Stopwatch::class)
            ->setMethods(['isStarted', 'stop'])
            ->getMock();
        $stopwatch->expects(self::once())
            ->method('isStarted')
            ->willReturn(true);
        $stopwatch->expects(self::exactly(3))
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
        self::assertTrue($called1);
        self::assertFalse($called2);
        $dispatcher->dispatch(new Event(), 'my-event');
        self::assertTrue($called2);
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

        self::assertCount(1, $eventDispatcher->getListeners('foo'), 'expected listener1 to be removed');
    }

    protected function getHttpKernel($dispatcher)
    {
        $controllerResolver = self::createMock(ControllerResolverInterface::class);
        $controllerResolver->expects(self::once())->method('getController')->willReturn(function () {
            return new Response();
        });
        $argumentResolver = self::createMock(ArgumentResolverInterface::class);
        $argumentResolver->expects(self::once())->method('getArguments')->willReturn([]);

        return new HttpKernel($dispatcher, $controllerResolver, new RequestStack(), $argumentResolver);
    }
}
