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

use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\CompoundLimiter;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class CompoundRateLimiterFactoryTest extends TestCase
{
    public function testCreate()
    {
        $factory1 = $this->createMock(RateLimiterFactoryInterface::class);
        $factory1
            ->expects($this->once())
            ->method('create')
            ->with('foo')
            ->willReturn($this->createStub(LimiterInterface::class))
        ;
        $factory2 = $this->createMock(RateLimiterFactoryInterface::class);
        $factory2
            ->expects($this->once())
            ->method('create')
            ->with('foo')
            ->willReturn($this->createStub(LimiterInterface::class))
        ;

        $compoundFactory = new CompoundRateLimiterFactory([$factory1, $factory2]);

        $this->assertInstanceOf(CompoundLimiter::class, $compoundFactory->create('foo'));
    }

    public function testCreateWithKeys()
    {
        $perUser = $this->createMock(RateLimiterFactoryInterface::class);
        $perUser
            ->expects($this->once())
            ->method('create')
            ->with('foo')
            ->willReturn($this->createStub(LimiterInterface::class))
        ;
        $globalQuota = $this->createMock(RateLimiterFactoryInterface::class);
        $globalQuota
            ->expects($this->once())
            ->method('create')
            ->with('global')
            ->willReturn($this->createStub(LimiterInterface::class))
        ;

        $compoundFactory = new CompoundRateLimiterFactory(
            ['per_user' => $perUser, 'global_quota' => $globalQuota],
            ['global_quota' => 'global'],
        );

        $this->assertInstanceOf(CompoundLimiter::class, $compoundFactory->create('foo'));
    }

    public function testCreateThrowsOnUnknownFactoryInKeys()
    {
        $factory1 = $this->createMock(RateLimiterFactoryInterface::class);
        $factory1
            ->expects($this->once())
            ->method('create')
            ->with('foo')
            ->willReturn($this->createStub(LimiterInterface::class))
        ;
        $factory2 = $this->createMock(RateLimiterFactoryInterface::class);
        $factory2
            ->expects($this->once())
            ->method('create')
            ->with('foo')
            ->willReturn($this->createStub(LimiterInterface::class))
        ;

        $compoundFactory = new CompoundRateLimiterFactory(
            ['first' => $factory1, 'second' => $factory2],
            ['unknown' => 'global'],
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown rate limiter(s) "unknown" in the "$keys" argument of "Symfony\Component\RateLimiter\CompoundRateLimiterFactory".');

        $compoundFactory->create('foo');
    }
}
