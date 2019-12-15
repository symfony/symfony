<?php

namespace Symfony\Component\EventDispatcher\Tests\ListenerProvider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\ListenerProvider\SimpleListenerProvider;
use Symfony\Contracts\EventDispatcher\Event;

final class SimpleListenerProviderTest extends TestCase
{
    public function testEmptyProvider(): void
    {
        $dispatcher = new SimpleListenerProvider([]);

        $this->assertSame([], $dispatcher->getListenersForEvent(new Event()));
    }

    public function testArray(): void
    {
        $dispatcher = new SimpleListenerProvider([
            $one = static function (): void {},
            $two = static function (): void {},
            $three = static function (): void {},
        ]);

        $this->assertSame([$one, $two, $three], $dispatcher->getListenersForEvent(new Event()));
    }

    public function testIterator(): void
    {
        $dispatcher = new SimpleListenerProvider(new \ArrayIterator([
            $one = static function (): void {},
            $two = static function (): void {},
            $three = static function (): void {},
        ]));

        $this->assertSame([$one, $two, $three], iterator_to_array($dispatcher->getListenersForEvent(new Event())));
    }
}
