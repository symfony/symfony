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
            ->method('create')
            ->with('foo')
            ->willReturn($this->createMock(LimiterInterface::class))
        ;
        $factory2 = $this->createMock(RateLimiterFactoryInterface::class);
        $factory2
            ->method('create')
            ->with('foo')
            ->willReturn($this->createMock(LimiterInterface::class))
        ;

        $compoundFactory = new CompoundRateLimiterFactory([$factory1, $factory2]);

        $this->assertInstanceOf(CompoundLimiter::class, $compoundFactory->create('foo'));
    }
}
