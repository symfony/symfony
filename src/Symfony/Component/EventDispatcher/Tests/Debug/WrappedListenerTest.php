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

class WrappedListenerTest extends TestCase
{
    /**
     * @dataProvider provideListenersToDescribe
     */
    public function testListenerDescription(callable $listener, $expected)
    {
        $wrappedListener = new WrappedListener($listener, null, $this->getMockBuilder(Stopwatch::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock());

        $this->assertStringMatchesFormat($expected, $wrappedListener->getPretty());
    }

    public function provideListenersToDescribe()
    {
        return [
            [new FooListener(), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::__invoke'],
            [[new FooListener(), 'listen'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'],
            [['Symfony\Component\EventDispatcher\Tests\Debug\FooListener', 'listenStatic'], 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic'],
            ['var_dump', 'var_dump'],
            [function () {}, 'closure'],
            [\Closure::fromCallable([new FooListener(), 'listen']), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'],
            [\Closure::fromCallable(['Symfony\Component\EventDispatcher\Tests\Debug\FooListener', 'listenStatic']), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic'],
            [\Closure::fromCallable(function () {}), 'closure'],
        ];
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
