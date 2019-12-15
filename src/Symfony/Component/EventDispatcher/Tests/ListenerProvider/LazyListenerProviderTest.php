<?php

namespace Symfony\Component\EventDispatcher\Tests\ListenerProvider;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use Symfony\Component\EventDispatcher\ListenerProvider\LazyListenerProvider;

final class LazyListenerProviderTest extends TestCase
{
    public function testGetListenersForEvent(): void
    {
        $calls = 0;

        $expectedEvent = new class() {
        };
        $expectedResult = new \EmptyIterator();

        $innerProvider = $this->createMock(ListenerProviderInterface::class);
        $innerProvider
            ->expects($this->exactly(2))
            ->method('getListenersForEvent')
            ->with($this->identicalTo($expectedEvent))
            ->willReturn($expectedResult);

        $provider = new LazyListenerProvider(static function () use (&$calls, $innerProvider): ListenerProviderInterface {
            ++$calls;

            return $innerProvider;
        });

        $this->assertSame(0, $calls);
        $this->assertSame($expectedResult, $provider->getListenersForEvent($expectedEvent));
        $this->assertSame($expectedResult, $provider->getListenersForEvent($expectedEvent));
        $this->assertSame(1, $calls);
    }

    public function testPassthrough(): void
    {
        $innerProvider = new class() implements ListenerProviderInterface {
            private $calls = 0;

            public function getListenersForEvent(object $event): iterable
            {
                return [];
            }

            public function increment(): int
            {
                return ++$this->calls;
            }
        };

        $provider = new LazyListenerProvider(static function () use ($innerProvider): ListenerProviderInterface {
            return $innerProvider;
        });

        $this->assertSame(1, $provider->increment());
        $this->assertSame(2, $provider->increment());
    }
}
