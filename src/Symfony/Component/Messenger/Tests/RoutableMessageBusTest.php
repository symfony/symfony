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
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class RoutableMessageBusTest extends TestCase
{
    public function testItRoutesToTheCorrectBus()
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('foo_bus')]);

        $bus1 = $this->createMock(MessageBusInterface::class);
        $bus2 = $this->createMock(MessageBusInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('has')->with('foo_bus')->willReturn(true);
        $container->expects($this->once())->method('get')->willReturn($bus2);

        $stamp = new DelayStamp(5);
        $bus1->expects($this->never())->method('dispatch');
        $bus2->expects($this->once())->method('dispatch')->with($envelope, [$stamp])->willReturn($envelope);

        $routableBus = new RoutableMessageBus($container);
        $this->assertSame($envelope, $routableBus->dispatch($envelope, [$stamp]));
    }

    public function testItRoutesToDefaultBus()
    {
        $envelope = new Envelope(new \stdClass());
        $stamp = new DelayStamp(5);
        $defaultBus = $this->createMock(MessageBusInterface::class);
        $defaultBus->expects($this->once())->method('dispatch')->with($envelope, [$stamp])
            ->willReturn($envelope);

        $container = $this->createMock(ContainerInterface::class);

        $routableBus = new RoutableMessageBus($container, $defaultBus);

        $this->assertSame($envelope, $routableBus->dispatch($envelope, [$stamp]));
    }

    public function testItExceptionOnBusNotFound()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bus named "my_cool_bus" does not exist.');

        $envelope = new Envelope(new \stdClass(), [
            new BusNameStamp('my_cool_bus'),
        ]);

        $container = $this->createMock(ContainerInterface::class);
        $routableBus = new RoutableMessageBus($container);
        $routableBus->dispatch($envelope);
    }

    public function testItExceptionOnDefaultBusNotFound()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Envelope is missing a BusNameStamp and no fallback message bus is configured on RoutableMessageBus.');

        $envelope = new Envelope(new \stdClass());

        $container = $this->createMock(ContainerInterface::class);
        $routableBus = new RoutableMessageBus($container);
        $routableBus->dispatch($envelope);
    }
}
