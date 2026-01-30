<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\NoLock;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\Checkpoint;

class CheckpointTest extends TestCase
{
    public function testWithoutLockAndWithoutState(): void
    {
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');
        $later = $now->modify('1 hour');
        $checkpoint = new Checkpoint('dummy');

        $this->assertTrue($checkpoint->acquire($now));
        $this->assertSame($now, $checkpoint->time());
        $this->assertSame(-1, $checkpoint->index());

        $checkpoint->save($later, 7);

        $this->assertSame($later, $checkpoint->time());
        $this->assertSame(7, $checkpoint->index());

        $checkpoint->release($later, null);
    }

    public function testWithStateInitStateOnFirstAcquiring(): void
    {
        $checkpoint = new Checkpoint('cache', new NoLock(), $cache = new ArrayAdapter());
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $this->assertTrue($checkpoint->acquire($now));
        $this->assertEquals($now, $checkpoint->time());
        $this->assertEquals(-1, $checkpoint->index());
        $this->assertEquals([$now, -1, $now], $cache->get('cache', static fn () => []));
    }

    public function testWithStateLoadStateOnAcquiring(): void
    {
        $checkpoint = new Checkpoint('cache', new NoLock(), $cache = new ArrayAdapter());
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $cache->get('cache', static fn () => [$now, 0], \INF);

        $this->assertTrue($checkpoint->acquire($startedAt = $now->modify('1 min')));
        $this->assertEquals($now, $checkpoint->time());
        $this->assertEquals(0, $checkpoint->index());
        $this->assertEquals([$now, 0, $startedAt], $cache->get('cache', static fn () => []));
    }

    public function testWithLockInitStateOnFirstAcquiring(): void
    {
        $lock = new Lock(new Key('lock'), new InMemoryStore());
        $checkpoint = new Checkpoint('dummy', $lock);
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $this->assertTrue($checkpoint->acquire($now));
        $this->assertEquals($now, $checkpoint->time());
        $this->assertEquals($now, $checkpoint->from());
        $this->assertEquals(-1, $checkpoint->index());
        $this->assertTrue($lock->isAcquired());
    }

    public function testWithLockLoadStateOnAcquiring(): void
    {
        $lock = new Lock(new Key('lock'), new InMemoryStore());
        $checkpoint = new Checkpoint('dummy', $lock);
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $checkpoint->save($now, 0);

        $this->assertTrue($checkpoint->acquire($now->modify('1 min')));
        $this->assertEquals($now, $checkpoint->time());
        $this->assertEquals($now, $checkpoint->from());
        $this->assertEquals(0, $checkpoint->index());
        $this->assertTrue($lock->isAcquired());
    }

    public function testWithLockCannotAcquireIfAlreadyAcquired(): void
    {
        $concurrentLock = new Lock(new Key('locked'), $store = new InMemoryStore(), autoRelease: false);
        $concurrentLock->acquire();
        $this->assertTrue($concurrentLock->isAcquired());

        $lock = new Lock(new Key('locked'), $store, autoRelease: false);
        $checkpoint = new Checkpoint('locked', $lock);
        $this->assertFalse($checkpoint->acquire(new \DateTimeImmutable()));
    }

    public function testWithCacheSave(): void
    {
        $checkpoint = new Checkpoint('cache', new NoLock(), $cache = new ArrayAdapter());
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');
        $checkpoint->acquire($startedAt = $now->modify('-1 hour'));
        $checkpoint->save($now, 3);

        $this->assertSame($now, $checkpoint->time());
        $this->assertSame(3, $checkpoint->index());
        $this->assertSame($startedAt, $checkpoint->from());
        $this->assertEquals([$now, 3, $startedAt], $cache->get('cache', static fn () => []));
    }

    public function testWithLockSave(): void
    {
        $lock = new Lock(new Key('lock'), new InMemoryStore());
        $checkpoint = new Checkpoint('dummy', $lock);
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $checkpoint->acquire($startTime = $now->modify('-1 hour'));
        $checkpoint->save($now, 3);

        $this->assertSame($now, $checkpoint->time());
        $this->assertSame(3, $checkpoint->index());
        $this->assertSame($startTime, $checkpoint->from());
    }

    public function testWithLockAndCacheSave(): void
    {
        $lock = new Lock(new Key('lock'), new InMemoryStore());
        $checkpoint = new Checkpoint('dummy', $lock, $cache = new ArrayAdapter());
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $checkpoint->acquire($startTime = $now->modify('-1 hour'));
        $checkpoint->save($now, 3);

        $this->assertSame($now, $checkpoint->time());
        $this->assertSame(3, $checkpoint->index());
        $this->assertEquals([$now, 3, $startTime], $cache->get('dummy', static fn () => []));
    }

    public function testWithCacheFullCycle(): void
    {
        $checkpoint = new Checkpoint('cache', new NoLock(), $cache = new ArrayAdapter());
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        // init
        $cache->get('cache', static fn () => [$now->modify('-1 min'), 3], \INF);

        // action
        $acquired = $checkpoint->acquire($now);
        $lastTime = $checkpoint->time();
        $lastIndex = $checkpoint->index();
        $checkpoint->save($now, 0);
        $checkpoint->release($now, null);

        // asserting
        $this->assertTrue($acquired);
        $this->assertEquals($now->modify('-1 min'), $lastTime);
        $this->assertSame(3, $lastIndex);
        $this->assertEquals($now, $checkpoint->time());
        $this->assertSame(0, $checkpoint->index());
        $this->assertEquals([$now, 0, $now], $cache->get('cache', static fn () => []));
    }

    public function testWithLockResetStateAfterLockedAcquiring(): void
    {
        $concurrentLock = new Lock(new Key('locked'), $store = new InMemoryStore(), autoRelease: false);
        $concurrentLock->acquire();
        $this->assertTrue($concurrentLock->isAcquired());

        $lock = new Lock(new Key('locked'), $store, autoRelease: false);
        $checkpoint = new Checkpoint('locked', $lock);
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $checkpoint->save($now->modify('-2 min'), 0);
        $checkpoint->acquire($now->modify('-1 min'));

        $concurrentLock->release();

        $this->assertTrue($checkpoint->acquire($now));
        $this->assertEquals($now, $checkpoint->time());
        $this->assertEquals(-1, $checkpoint->index());
        $this->assertTrue($lock->isAcquired());
        $this->assertFalse($concurrentLock->isAcquired());
    }

    public function testWithLockResetStateAfterLockedAcquiringCache(): void
    {
        $concurrentLock = new Lock(new Key('locked'), $store = new InMemoryStore(), autoRelease: false);
        $concurrentLock->acquire();
        $this->assertTrue($concurrentLock->isAcquired());

        $lock = new Lock(new Key('locked'), $store, autoRelease: false);
        $checkpoint = new Checkpoint('locked', $lock, $cache = new ArrayAdapter());
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $checkpoint->save($savedTime = $now->modify('-2 min'), $savedIndex = 0);
        $checkpoint->acquire($now->modify('-1 min'));

        $two = new Checkpoint('locked', $lock, $cache);

        $concurrentLock->release();

        $this->assertTrue($two->acquire($now));
        $this->assertEquals($savedTime, $two->time());
        $this->assertEquals($savedIndex, $two->index());
        $this->assertTrue($lock->isAcquired());
        $this->assertFalse($concurrentLock->isAcquired());
    }

    public function testWithLockKeepLock(): void
    {
        $lock = new Lock(new Key('lock'), new InMemoryStore());
        $checkpoint = new Checkpoint('dummy', $lock);
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $checkpoint->acquire($now->modify('-1 min'));
        $checkpoint->release($now, $now->modify('1 min'));

        $this->assertTrue($lock->isAcquired());
    }

    public function testWithLockReleaseLock(): void
    {
        $lock = new Lock(new Key('lock'), new InMemoryStore());
        $checkpoint = new Checkpoint('dummy', $lock);
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $checkpoint->acquire($now->modify('-1 min'));
        $checkpoint->release($now, null);

        $this->assertFalse($lock->isAcquired());
    }

    public function testWithLockRefreshLock(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->method('getRemainingLifetime')->willReturn(120.0);
        $lock->expects($this->once())->method('refresh')->with(120.0 + 60.0);
        $lock->expects($this->never())->method('release');

        $checkpoint = new Checkpoint('dummy', $lock);
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        $checkpoint->acquire($now->modify('-10 sec'));
        $checkpoint->release($now, $now->modify('60 sec'));
    }

    public function testWithLockFullCycle(): void
    {
        $lock = new Lock(new Key('lock'), new InMemoryStore());
        $checkpoint = new Checkpoint('dummy', $lock);
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');

        // init
        $checkpoint->save($now->modify('-1 min'), 3);

        // action
        $acquired = $checkpoint->acquire($now);
        $lastTime = $checkpoint->time();
        $lastIndex = $checkpoint->index();
        $checkpoint->save($now, 0);
        $checkpoint->release($now, null);

        // asserting
        $this->assertTrue($acquired);
        $this->assertEquals($now->modify('-1 min'), $lastTime);
        $this->assertSame(3, $lastIndex);
        $this->assertEquals($now, $checkpoint->time());
        $this->assertSame(0, $checkpoint->index());
        $this->assertFalse($lock->isAcquired());
    }
}
