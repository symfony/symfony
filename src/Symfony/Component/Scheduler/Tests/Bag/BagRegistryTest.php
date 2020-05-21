<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Bag;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bag\BagInterface;
use Symfony\Component\Scheduler\Bag\BagRegistry;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BagRegistryTest extends TestCase
{
    public function testWorkerCannotBeFoundWithoutBeingRegistered(): void
    {
        $registry = new BagRegistry();

        static::expectException(InvalidArgumentException::class);
        $registry->get('foo');
    }

    public function testBagCannotBeRegisteredWhenExisting(): void
    {
        $task = new NullTask('foo');
        $task->set('arrival_time', new \DateTimeImmutable());

        $registry = new BagRegistry();
        $registry->register($task, new FooBag());
        static::assertTrue($registry->has($task->getBag('foo_bag')));

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('This bag is already registered.');
        $registry->register($task, new FooBag());
    }

    public function testBagCanBeRegistered(): void
    {
        $task = new NullTask('foo');
        $task->set('arrival_time', new \DateTimeImmutable());

        $registry = new BagRegistry();
        $registry->register($task, new FooBag());

        static::assertSame(1, $registry->count());
        static::assertTrue($registry->has($task->getBag('foo_bag')));
        static::assertNotNull($task->getBag('foo_bag'));
        static::assertInstanceOf(BagInterface::class, $registry->get($task->getBag('foo_bag')));
    }

    public function testBagCanBeFiltered(): void
    {
        $task = new NullTask('foo');
        $task->set('arrival_time', new \DateTimeImmutable());

        $registry = new BagRegistry();
        $registry->register($task, new FooBag());

        $bags = $registry->filter(function (BagInterface $bag): bool {
            return $bag instanceof FooBag;
        });

        static::assertNotEmpty($bags);
    }

    public function testBagCanBeRemoved(): void
    {
        $task = new NullTask('foo');
        $task->set('arrival_time', new \DateTimeImmutable());

        $registry = new BagRegistry();
        $registry->register($task, new FooBag());
        static::assertTrue($registry->has($task->getBag('foo_bag')));

        $registry->remove($task->getBag('foo_bag'));
        static::assertSame(0, $registry->count());
    }
}

final class FooBag implements BagInterface
{
    /**
     * {@inheritdoc}
     */
    public function getContent(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'foo';
    }
}
