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
        $listeners = array(
            array(new FooListener(), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::__invoke'),
            array(array(new FooListener(), 'listen'), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen'),
            array(array('Symfony\Component\EventDispatcher\Tests\Debug\FooListener', 'listenStatic'), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic'),
            array('var_dump', 'var_dump'),
            array(function () {}, 'closure'),
        );

        if (\PHP_VERSION_ID >= 70100) {
            $listeners[] = array(\Closure::fromCallable(array(new FooListener(), 'listen')), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listen');
            $listeners[] = array(\Closure::fromCallable(array('Symfony\Component\EventDispatcher\Tests\Debug\FooListener', 'listenStatic')), 'Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic');
            $listeners[] = array(\Closure::fromCallable(function () {}), 'closure');
        }

        return $listeners;
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
