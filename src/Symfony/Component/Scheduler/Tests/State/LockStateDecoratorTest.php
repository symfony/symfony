<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\State;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\State\LockStateDecorator;
use Symfony\Component\Scheduler\State\State;

class LockStateDecoratorTest extends TestCase
{
    private InMemoryStore $store;
    private Lock $lock;
    private State $inner;
    private LockStateDecorator $state;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->lock = new Lock(new Key('lock'), $this->store);
        $this->inner = new State();
        $this->state = new LockStateDecorator($this->inner, $this->lock);
        $this->now = new \DateTimeImmutable('2020-02-20 20:20:20Z');
    }

    public function testSave()
    {
        [$inner, $state, $now] = [$this->inner, $this->state, $this->now];

        $state->acquire($now->modify('-1 hour'));
        $state->save($now, 3);

        $this->assertSame($now, $state->time());
        $this->assertSame(3, $state->index());
        $this->assertSame($inner->time(), $state->time());
        $this->assertSame($inner->index(), $state->index());
    }

    public function testInitStateOnFirstAcquiring()
    {
        [$lock, $state, $now] = [$this->lock, $this->state, $this->now];

        $this->assertTrue($state->acquire($now));
        $this->assertEquals($now, $state->time());
        $this->assertEquals(-1, $state->index());
        $this->assertTrue($lock->isAcquired());
    }

    public function testLoadStateOnAcquiring()
    {
        [$lock, $inner, $state, $now] = [$this->lock, $this->inner, $this->state, $this->now];

        $inner->save($now, 0);

        $this->assertTrue($state->acquire($now->modify('1 min')));
        $this->assertEquals($now, $state->time());
        $this->assertEquals(0, $state->index());
        $this->assertTrue($lock->isAcquired());
    }

    public function testCannotAcquereIfLocked()
    {
        [$state, $now] = [$this->state, $this->now];

        $this->concurrentLock();

        $this->assertFalse($state->acquire($now));
    }

    public function testResetStateAfterLockedAcquiring()
    {
        [$lock, $inner, $state, $now] = [$this->lock, $this->inner, $this->state, $this->now];

        $concurrentLock = $this->concurrentLock();
        $inner->save($now->modify('-2 min'), 0);
        $state->acquire($now->modify('-1 min'));
        $concurrentLock->release();

        $this->assertTrue($state->acquire($now));
        $this->assertEquals($now, $state->time());
        $this->assertEquals(-1, $state->index());
        $this->assertTrue($lock->isAcquired());
        $this->assertFalse($concurrentLock->isAcquired());
    }

    public function testKeepLock()
    {
        [$lock, $state, $now] = [$this->lock, $this->state, $this->now];

        $state->acquire($now->modify('-1 min'));
        $state->release($now, $now->modify('1 min'));

        $this->assertTrue($lock->isAcquired());
    }

    public function testReleaseLock()
    {
        [$lock, $state, $now] = [$this->lock, $this->state, $this->now];

        $state->acquire($now->modify('-1 min'));
        $state->release($now, null);

        $this->assertFalse($lock->isAcquired());
    }

    public function testRefreshLock()
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->method('getRemainingLifetime')->willReturn(120.0);
        $lock->expects($this->once())->method('refresh')->with(120.0 + 60.0);
        $lock->expects($this->never())->method('release');

        $state = new LockStateDecorator(new State(), $lock);
        $now = $this->now;

        $state->acquire($now->modify('-10 sec'));
        $state->release($now, $now->modify('60 sec'));
    }

    public function testFullCycle()
    {
        [$lock, $inner, $state, $now] = [$this->lock, $this->inner, $this->state, $this->now];

        // init
        $inner->save($now->modify('-1 min'), 3);

        // action
        $acquired = $state->acquire($now);
        $lastTime = $state->time();
        $lastIndex = $state->index();
        $state->save($now, 0);
        $state->release($now, null);

        // asserting
        $this->assertTrue($acquired);
        $this->assertEquals($now->modify('-1 min'), $lastTime);
        $this->assertSame(3, $lastIndex);
        $this->assertEquals($now, $inner->time());
        $this->assertSame(0, $inner->index());
        $this->assertFalse($lock->isAcquired());
    }

    // No need to unlock after test, because the `InMemoryStore` is deleted
    private function concurrentLock(): Lock
    {
        $lock = new Lock(
            key: new Key('lock'),
            store: $this->store,
            autoRelease: false
        );

        if (!$lock->acquire()) {
            throw new \LogicException('Already locked.');
        }

        return $lock;
    }
}
