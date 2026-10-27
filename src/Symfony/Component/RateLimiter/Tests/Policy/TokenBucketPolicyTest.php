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
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\Policy\TokenBucketPolicy;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class TokenBucketPolicyTest extends TestCase
{
    public function testCreateFactory()
    {
        $limiter = (new TokenBucketPolicy(2, '1 hour'))->createFactory(new RateLimiterBuilder(new InMemoryStorage()), 'id')->create('key');

        $this->assertInstanceOf(TokenBucketLimiter::class, $limiter);
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertFalse($limiter->consume()->isAccepted());
    }
}
