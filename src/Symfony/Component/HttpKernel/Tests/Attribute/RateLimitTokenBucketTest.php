<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Attribute\RateLimitTokenBucket;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RateLimitTokenBucketTest extends TestCase
{
    public function testDefaults()
    {
        $rl = new RateLimitTokenBucket(10, '1 hour');

        $this->assertSame(10, $rl->limit);
        $this->assertSame('1 hour', $rl->interval);
        $this->assertSame(1, $rl->amount);
        $this->assertNull($rl->key);
        $this->assertSame(1, $rl->tokens);
        $this->assertSame([], $rl->methods);
        $this->assertFalse($rl->exposeHeaders);
        $this->assertTrue($rl->if);
        $this->assertNull($rl->id);
    }

    public function testTokensMustBePositive()
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimitTokenBucket(10, '1 hour', tokens: 0);
    }

    public function testMethodsAreNormalized()
    {
        $rl = new RateLimitTokenBucket(10, '1 hour', methods: ['get', 'post']);

        $this->assertSame(['GET', 'POST', 'HEAD'], $rl->methods);
    }

    public function testCreateFactory()
    {
        $factory = (new RateLimitTokenBucket(2, '1 hour'))->createFactory(new RateLimiterBuilder(new InMemoryStorage()), 'id');
        $limiter = $factory->create('key');

        $this->assertInstanceOf(TokenBucketLimiter::class, $limiter);
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertFalse($limiter->consume()->isAccepted());
    }
}
