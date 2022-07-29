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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Scheduler\State\CacheStateDecorator;
use Symfony\Component\Scheduler\State\State;
use Symfony\Component\Scheduler\State\StateInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CacheStateDecoratorTest extends TestCase
{
    private ArrayAdapter $cache;
    private State $inner;
    private CacheStateDecorator $state;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter(storeSerialized: false);
        $this->inner = new State();
        $this->state = new CacheStateDecorator($this->inner, $this->cache, 'cache');
        $this->now = new \DateTimeImmutable('2020-02-20 20:20:20Z');
    }

    public function testInitStateOnFirstAcquiring()
    {
        [$cache, $state, $now] = [$this->cache, $this->state, $this->now];

        $this->assertTrue($state->acquire($now));
        $this->assertEquals($now, $state->time());
        $this->assertEquals(-1, $state->index());
        $this->assertEquals([$now, -1], $cache->get('cache', fn () => []));
    }

    public function testLoadStateOnAcquiring()
    {
        [$cache, $inner, $state, $now] = [$this->cache, $this->inner, $this->state, $this->now];

        $cache->get('cache', fn () => [$now, 0], \INF);

        $this->assertTrue($state->acquire($now->modify('1 min')));
        $this->assertEquals($now, $state->time());
        $this->assertEquals(0, $state->index());
        $this->assertEquals([$now, 0], $cache->get('cache', fn () => []));
    }

    public function testCannotAcquereIfInnerAcquered()
    {
        $inner = $this->createMock(StateInterface::class);
        $inner->method('acquire')->willReturn(false);
        $state = new CacheStateDecorator($inner, $this->cache, 'cache');

        $this->assertFalse($state->acquire($this->now));
    }

    public function testSave()
    {
        [$cache, $inner, $state, $now] = [$this->cache, $this->inner, $this->state, $this->now];

        $state->acquire($now->modify('-1 hour'));
        $state->save($now, 3);

        $this->assertSame($now, $state->time());
        $this->assertSame(3, $state->index());
        $this->assertSame($inner->time(), $state->time());
        $this->assertSame($inner->index(), $state->index());
        $this->assertSame([$now, 3], $cache->get('cache', fn () => []));
    }

    public function testRelease()
    {
        $now = $this->now;
        $later = $now->modify('1 min');
        $cache = $this->createMock(CacheInterface::class);
        $inner = $this->createMock(StateInterface::class);
        $inner->expects($this->once())->method('release')->with($now, $later);
        $state = new CacheStateDecorator($inner, $cache, 'cache');

        $state->release($now, $later);
    }

    public function testFullCycle()
    {
        [$cache, $inner, $state, $now] = [$this->cache, $this->inner, $this->state, $this->now];

        // init
        $cache->get('cache', fn () => [$now->modify('-1 min'), 3], \INF);

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
        $this->assertEquals([$now, 0], $cache->get('cache', fn () => []));
    }
}
