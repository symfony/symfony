<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistry;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerRegistryTest extends TestCase
{
    public function testSchedulerCannotBeFoundWithoutBeingRegistered(): void
    {
        $registry = new SchedulerRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->get('foo');
    }

    public function testSchedulerCannotBeReturned(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $registry = new SchedulerRegistry();

        $registry->register('foo', $scheduler);

        static::assertInstanceOf(SchedulerInterface::class, $registry->get('foo'));
    }

    public function testSchedulerCanBeSearched(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $registry = new SchedulerRegistry();
        static::assertFalse($registry->has('foo'));

        $registry->register('foo', $scheduler);
        static::assertTrue($registry->has('foo'));
    }

    public function testSchedulerCannotBeRegisteredWhenItAlreadyExist(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $registry = new SchedulerRegistry();
        $registry->register('foo', $scheduler);

        static::expectException(InvalidArgumentException::class);
        $registry->register('foo', $scheduler);
    }

    public function testSchedulerCanBeRegistered(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $registry = new SchedulerRegistry();
        $registry->register('foo', $scheduler);

        static::assertSame(1, $registry->count());
    }

    public function testSchedulerCannotBeRemovedWhenItDoesNotExist(): void
    {
        $registry = new SchedulerRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->remove('foo');
    }

    public function testSchedulerCanBeRemoved(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $registry = new SchedulerRegistry();

        $registry->register('foo', $scheduler);
        static::assertSame(1, $registry->count());

        $registry->remove('foo');
        static::assertSame(0, $registry->count());
    }

    public function testSchedulerCannotBeOverrideWhenItDoesNotExist(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $registry = new SchedulerRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->override('foo', $scheduler);
    }

    public function testSchedulerCanBeOverride(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $registry = new SchedulerRegistry();

        $registry->register('foo', $scheduler);
        static::assertSame(1, $registry->count());

        $registry->override('foo', $scheduler);
        static::assertSame(1, $registry->count());
    }

    public function testSchedulersCanBeRetrievedAsArray(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $registry = new SchedulerRegistry();

        $registry->register('foo', $scheduler);

        static::assertNotEmpty($registry->toArray());
        static::assertArrayHasKey('foo', $registry->toArray());
    }

    public function testSchedulersCanBeFiltered(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));

        $registry = new SchedulerRegistry();
        $registry->register('foo', $scheduler);

        static::assertNotEmpty($registry->filter(function (SchedulerInterface $scheduler): bool {
            return 'Europe/Paris' === $scheduler->getTimezone()->getName();
        }));

        static::assertArrayHasKey('foo', $registry->toArray());
    }
}
