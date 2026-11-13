<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Attribute;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\Attribute\FixedWindowPolicy;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[Group('time-sensitive')]
class FixedWindowPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        ClockMock::register(InMemoryStorage::class);
        ClockMock::register(RateLimit::class);
        ClockMock::register(FixedWindowLimiter::class);
    }

    public function testCreateFactory()
    {
        $limiter = (new FixedWindowPolicy(2, '1 hour'))->createFactory(new RateLimiterBuilder(new InMemoryStorage()), 'id')->create('key');

        $this->assertInstanceOf(FixedWindowLimiter::class, $limiter);
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertFalse($limiter->consume()->isAccepted());
    }
}
