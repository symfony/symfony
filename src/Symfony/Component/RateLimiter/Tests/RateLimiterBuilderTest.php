<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\CompoundLimiter;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RateLimiterBuilderTest extends TestCase
{
    public function testCreateMethodsReturnFactories()
    {
        $builder = new RateLimiterBuilder(new InMemoryStorage());

        $this->assertInstanceOf(RateLimiterFactoryInterface::class, $builder->slidingWindow('foo', 5, '1 minute'));
        $this->assertInstanceOf(RateLimiterFactoryInterface::class, $builder->fixedWindow('foo', 5, '1 minute'));
        $this->assertInstanceOf(RateLimiterFactoryInterface::class, $builder->tokenBucket('foo', 5, '1 hour', 2));
        $this->assertInstanceOf(RateLimiterFactoryInterface::class, $builder->noop());
        $this->assertInstanceOf(CompoundRateLimiterFactory::class, $builder->compound($builder->noop()));
    }

    public function testFactoriesCreateExpectedLimiters()
    {
        $builder = new RateLimiterBuilder(new InMemoryStorage());

        $this->assertInstanceOf(SlidingWindowLimiter::class, $builder->slidingWindow('foo', 5, '1 minute')->create());
        $this->assertInstanceOf(FixedWindowLimiter::class, $builder->fixedWindow('foo', 5, '1 minute')->create());
        $this->assertInstanceOf(TokenBucketLimiter::class, $builder->tokenBucket('foo', 5, '1 hour', 2)->create());
        $this->assertInstanceOf(NoLimiter::class, $builder->noop()->create());
        $this->assertInstanceOf(CompoundLimiter::class, $builder->compound($builder->noop())->create());
    }

    public function testAcceptsDateInterval()
    {
        $builder = new RateLimiterBuilder(new InMemoryStorage());
        $interval = new \DateInterval('PT1M');

        $this->assertInstanceOf(SlidingWindowLimiter::class, $builder->slidingWindow('foo', 5, $interval)->create());
        $this->assertInstanceOf(FixedWindowLimiter::class, $builder->fixedWindow('foo', 5, $interval)->create());
        $this->assertInstanceOf(TokenBucketLimiter::class, $builder->tokenBucket('foo', 5, $interval)->create());
    }

    #[DataProvider('anchorProvider')]
    public function testFixedWindowCanBeAnchoredToACalendar(\DateTimeInterface|string $anchorAt)
    {
        $builder = new RateLimiterBuilder(new InMemoryStorage());
        $limiter = $builder->fixedWindow('foo', 2, '1 month', $anchorAt)->create('key');
        $limiter->consume(2);

        // the window resets on the calendar boundary, not one month after the first hit
        $this->assertSame('01 00:00:00', $limiter->consume()->getRetryAfter()->format('d H:i:s'));
    }

    public static function anchorProvider()
    {
        yield 'string' => ['2024-01-01 00:00:00'];
        yield 'DateTimeImmutable' => [new \DateTimeImmutable('2024-01-01 00:00:00', new \DateTimeZone('UTC'))];
    }

    public function testLockIsCreatedPerKey()
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $builder = new RateLimiterBuilder(new InMemoryStorage(), $lockFactory);
        $factory = $builder->slidingWindow('foo', 5, '1 minute');

        $first = $factory->create('a');
        $first->consume();

        // a second key must not contend with the first one's lock
        $factory->create('b')->consume();

        $this->assertInstanceOf(SlidingWindowLimiter::class, $first);
    }
}
