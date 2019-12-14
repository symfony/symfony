<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Cron;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Cron\CronInterface;
use Symfony\Component\Scheduler\Cron\CronRegistry;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronRegistryTest extends TestCase
{
    public function testWorkerCannotBeFoundWithoutBeingRegistered(): void
    {
        $registry = new CronRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->get('foo');
    }

    public function testWorkerCannotBeReturned(): void
    {
        $cron = $this->createMock(CronInterface::class);

        $registry = new CronRegistry();
        $registry->register('foo', $cron);

        static::assertInstanceOf(CronInterface::class, $registry->get('foo'));
    }

    public function testWorkerCanBeSearched(): void
    {
        $cron = $this->createMock(CronInterface::class);

        $registry = new CronRegistry();
        static::assertFalse($registry->has('foo'));

        $registry->register('foo', $cron);
        static::assertTrue($registry->has('foo'));
    }

    public function testWorkerCannotBeRegisteredWhenItAlreadyExist(): void
    {
        $cron = $this->createMock(CronInterface::class);

        $registry = new CronRegistry();
        $registry->register('foo', $cron);

        static::expectException(InvalidArgumentException::class);
        $registry->register('foo', $cron);
    }

    public function testWorkerCanBeRegistered(): void
    {
        $cron = $this->createMock(CronInterface::class);

        $registry = new CronRegistry();
        $registry->register('foo', $cron);

        static::assertSame(1, $registry->count());
    }

    public function testWorkerCannotBeRemovedWhenItDoesNotExist(): void
    {
        $registry = new CronRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->remove('foo');
    }

    public function testWorkerCanBeRemoved(): void
    {
        $cron = $this->createMock(CronInterface::class);
        $registry = new CronRegistry();

        $registry->register('foo', $cron);
        static::assertSame(1, $registry->count());

        $registry->remove('foo');
        static::assertSame(0, $registry->count());
    }

    public function testWorkerCannotBeOverrideWhenItDoesNotExist(): void
    {
        $cron = $this->createMock(CronInterface::class);
        $registry = new CronRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->override('foo', $cron);
    }

    public function testWorkerCanBeOverride(): void
    {
        $cron = $this->createMock(CronInterface::class);
        $registry = new CronRegistry();

        $registry->register('foo', $cron);
        static::assertSame(1, $registry->count());

        $registry->override('foo', $cron);
        static::assertSame(1, $registry->count());
    }

    public function testWorkersCanBeRetrievedAsArray(): void
    {
        $cron = $this->createMock(CronInterface::class);

        $registry = new CronRegistry();
        $registry->register('foo', $cron);

        static::assertNotEmpty($registry->toArray());
        static::assertArrayHasKey('foo', $registry->toArray());
    }

    public function testWorkersCanBeFiltered(): void
    {
        $cron = $this->createMock(CronInterface::class);
        $cron->expects(self::once())->method('getExpression')->willReturn('* * * * *');

        $registry = new CronRegistry();
        $registry->register('foo', $cron);

        static::assertNotEmpty($registry->filter(function (CronInterface $cron): bool {
            return '* * * * *' === $cron->getExpression();
        }));

        static::assertArrayHasKey('foo', $registry->toArray());
    }
}
