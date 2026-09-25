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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Debug\WrappedListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;
use Symfony\Component\VarDumper\Caster\ClassStub;

class WrappedListenerTest extends TestCase
{
    #[DataProvider('provideListenersToDescribe')]
    public function testListenerDescription($listener, $expected)
    {
        $wrappedListener = new WrappedListener($listener, null, new Stopwatch(), new EventDispatcher());

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
            [static function () {}, 'closure'],
            [\Closure::fromCallable([new FooListener(), 'listen']), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'],
            [\Closure::fromCallable(['Symfony\Component\EventDispatcher\Tests\Debug\FooListener', 'listenStatic']), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic'],
            [\Closure::fromCallable(static function () {}), 'closure'],
            [[#[\Closure(name: FooListener::class)] static fn () => new FooListener(), 'listen'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'],
        ];
    }

    #[DataProvider('provideListenersWithCallable')]
    public function testInfoHoldsTheCallableOfTheListener($listener, ?string $expected)
    {
        $wrappedListener = new WrappedListener($listener, null, new Stopwatch(), new EventDispatcher());

        $this->assertSame($expected, $wrappedListener->getInfo('foo')['callable']);
    }

    public static function provideListenersWithCallable()
    {
        return [
            [new FooListener(), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::__invoke'],
            [[new FooListener(), 'listen'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'],
            [['Symfony\Component\EventDispatcher\Tests\Debug\FooListener', 'listenStatic'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic'],
            ['var_dump', 'var_dump'],
            [[#[\Closure(name: 'foo_listener', class: FooListener::class)] static fn () => new FooListener(), 'listen'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'],
            [static function () {}, null],
        ];
    }

    public function testStubIsBuiltOnFirstUse()
    {
        $wrappedListener = new WrappedListener([#[\Closure(name: 'foo_listener', class: FooListener::class)] static fn () => new FooListener(), 'listen'], null, new Stopwatch(), new EventDispatcher());
        $stub = $wrappedListener->getInfo('foo')['stub'];

        $this->assertTrue(new \ReflectionClass(ClassStub::class)->isUninitializedLazyObject($stub));

        $expected = new ClassStub('foo_listener::listen()', FooListener::class.'::listen');
        $this->assertSame($expected->value, $stub->value);
        $this->assertSame($expected->attr, $stub->attr);
    }

    public function testStopwatchEventIsStoppedWhenListenerThrows()
    {
        $stopwatchEvent = $this->createMock(StopwatchEvent::class);
        $stopwatchEvent->expects(self::once())->method('isStarted')->willReturn(true);
        $stopwatchEvent->expects(self::once())->method('stop');

        $stopwatch = $this->createStub(Stopwatch::class);
        $stopwatch->method('start')->willReturn($stopwatchEvent);

        $dispatcher = $this->createStub(EventDispatcherInterface::class);

        $wrappedListener = new WrappedListener(static fn () => throw new \Exception(), null, $stopwatch, $dispatcher);

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
