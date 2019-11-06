<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

class RoutableMessageBusTest extends TestCase
{
    public function testItRoutesToTheCorrectBus()
    {
        $envelope = new Envelope(new \stdClass(), [new ReceivedStamp('foo_receiver')]);

        $bus = $this->createMock(MessageBusInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('has')->with('foo_receiver')->willReturn(true);
        $container->expects($this->once())->method('get')->with('foo_receiver')->willReturn($bus);

        $stamp = new DelayStamp(5);
        $bus->expects($this->once())->method('dispatch')->with($envelope, [$stamp])->willReturn($envelope);

        $routableBus = new RoutableMessageBus($container);
        $this->assertSame($envelope, $routableBus->dispatch($envelope, [$stamp]));
    }

    public function testItRoutesToTheCorrectBusWhenComingFromFailureTransport()
    {
        $envelope = new Envelope(new \stdClass(), [new ReceivedStamp('failure_transport'), new SentToFailureTransportStamp('original_receiver')]);

        $bus = $this->createMock(MessageBusInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->atLeastOnce())->method('has')->with('original_receiver')->willReturn(true);
        $container->expects($this->once())->method('get')->with('original_receiver')->willReturn($bus);

        $stamp = new DelayStamp(5);
        $bus->expects($this->once())->method('dispatch')->willReturnArgument(0);

        $routableBus = new RoutableMessageBus($container);
        $this->assertEquals($envelope->with(new ReceivedStamp('original_receiver')), $routableBus->dispatch($envelope, [$stamp]));
    }

    public function testExceptionOnBusNotFound()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find a bus for transport "my_cool_receiver".');

        $envelope = new Envelope(new \stdClass(), [
            new ReceivedStamp('my_cool_receiver'),
        ]);

        $container = $this->createMock(ContainerInterface::class);
        $routableBus = new RoutableMessageBus($container);
        $routableBus->dispatch($envelope);
    }

    public function testExceptionOnReceivedStampNotFound()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Envelope is missing a ReceivedStamp.');

        $envelope = new Envelope(new \stdClass());

        $container = $this->createMock(ContainerInterface::class);
        $routableBus = new RoutableMessageBus($container);
        $routableBus->dispatch($envelope);
    }
}
