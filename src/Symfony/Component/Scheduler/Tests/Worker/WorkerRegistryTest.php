<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Worker;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Component\Scheduler\Worker\WorkerRegistry;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerRegistryTest extends TestCase
{
    public function testWorkerCannotBeFoundWithoutBeingRegistered(): void
    {
        $registry = new WorkerRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->get('foo');
    }

    public function testWorkerCannotBeReturned(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $registry = new WorkerRegistry();
        $registry->register('foo', $worker);

        static::assertInstanceOf(WorkerInterface::class, $registry->get('foo'));
    }

    public function testWorkerCanBeSearched(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $registry = new WorkerRegistry();
        static::assertFalse($registry->has('foo'));

        $registry->register('foo', $worker);
        static::assertTrue($registry->has('foo'));
    }

    public function testWorkerCannotBeRegisteredWhenItAlreadyExist(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $registry = new WorkerRegistry();
        $registry->register('foo', $worker);

        static::expectException(InvalidArgumentException::class);
        $registry->register('foo', $worker);
    }

    public function testWorkerCanBeRegistered(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $registry = new WorkerRegistry();
        $registry->register('foo', $worker);

        static::assertSame(1, $registry->count());
    }

    public function testWorkerCannotBeRemovedWhenItDoesNotExist(): void
    {
        $registry = new WorkerRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->remove('foo');
    }

    public function testWorkerCanBeRemoved(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $registry = new WorkerRegistry();

        $registry->register('foo', $worker);
        static::assertSame(1, $registry->count());

        $registry->remove('foo');
        static::assertSame(0, $registry->count());
    }

    public function testWorkerCannotBeOverrideWhenItDoesNotExist(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $registry = new WorkerRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->override('foo', $worker);
    }

    public function testWorkerCanBeOverride(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $registry = new WorkerRegistry();

        $registry->register('foo', $worker);
        static::assertSame(1, $registry->count());

        $registry->override('foo', $worker);
        static::assertSame(1, $registry->count());
    }

    public function testWorkersCanBeRetrievedAsArray(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $registry = new WorkerRegistry();
        $registry->register('foo', $worker);

        static::assertNotEmpty($registry->toArray());
        static::assertArrayHasKey('foo', $registry->toArray());
    }

    public function testWorkersCanBeFiltered(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(true);

        $registry = new WorkerRegistry();
        $registry->register('foo', $worker);

        static::assertNotEmpty($registry->filter(function (WorkerInterface $worker): bool {
            return $worker->isRunning();
        }));

        static::assertArrayHasKey('foo', $registry->toArray());
    }
}
