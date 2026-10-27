<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Policy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Policy\FixedWindowPolicy;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class FixedWindowPolicyTest extends TestCase
{
    public function testCreateFactory()
    {
        $limiter = (new FixedWindowPolicy(2, '1 hour'))->createFactory(new RateLimiterBuilder(new InMemoryStorage()), 'id')->create('key');

        $this->assertInstanceOf(FixedWindowLimiter::class, $limiter);
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertFalse($limiter->consume()->isAccepted());
    }
}
