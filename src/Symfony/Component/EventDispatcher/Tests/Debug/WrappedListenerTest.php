<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Debug\WrappedListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

class WrappedListenerTest extends TestCase
{
    /**
     * @dataProvider provideListenersToDescribe
     */
    public function testListenerDescription($listener, $expected)
    {
        $wrappedListener = new WrappedListener($listener, null, $this->createMock(Stopwatch::class), $this->createMock(EventDispatcherInterface::class));

        $this->assertStringMatchesFormat($expected, $wrappedListener->getPretty());
    }

    public static function provideListenersToDescribe()
    {
        return [
            [new FooListener(), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::__invoke'],
            [[new FooListener(), 'listen'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'],
            [['Symfony\Component\EventDispatcher\Tests\Debug\FooListener', 'listenStatic'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic'],
            [['Symfony\Component\EventDispatcher\Tests\Debug\FooListener', 'invalidMethod'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::invalidMethod'],
            ['var_dump', 'var_dump'],
            [function () {}, 'closure'],
            [\Closure::fromCallable([new FooListener(), 'listen']), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'],
            [\Closure::fromCallable(['Symfony\Component\EventDispatcher\Tests\Debug\FooListener', 'listenStatic']), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic'],
            [\Closure::fromCallable(function () {}), 'closure'],
            [[#[\Closure(name: FooListener::class)] static fn () => new FooListener(), 'listen'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'],
        ];
    }

    public function testStopwatchEventIsStoppedWhenListenerThrows()
    {
        $stopwatchEvent = $this->createMock(StopwatchEvent::class);
        $stopwatchEvent->expects(self::once())->method('isStarted')->willReturn(true);
        $stopwatchEvent->expects(self::once())->method('stop');

        $stopwatch = $this->createStub(Stopwatch::class);
        $stopwatch->method('start')->willReturn($stopwatchEvent);

        $dispatcher = $this->createStub(EventDispatcherInterface::class);

        $wrappedListener = new WrappedListener(function () { throw new \Exception(); }, null, $stopwatch, $dispatcher);

        try {
            $wrappedListener(new \stdClass(), 'foo', $dispatcher);
        } catch (\Exception $ex) {
        }
    }
}

class FooListener
{
    public function listen()
    {
    }

    public function __invoke()
    {
    }

    public static function listenStatic()
    {
    }
}
