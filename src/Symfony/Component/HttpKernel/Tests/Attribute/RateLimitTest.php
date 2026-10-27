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
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\RateLimiter\Policy\SlidingWindowPolicy;

class RateLimitTest extends TestCase
{
    public function testDefaults()
    {
        $rl = new RateLimit('api');

        $this->assertSame('api', $rl->limiter);
        $this->assertNull($rl->key);
        $this->assertSame(1, $rl->tokens);
        $this->assertSame([], $rl->methods);
        $this->assertFalse($rl->exposeHeaders);
    }

    public function testPolicy()
    {
        $policy = new SlidingWindowPolicy(3, '1 hour');

        $this->assertSame($policy, (new RateLimit($policy))->limiter);
    }

    public function testTokensMustBePositive()
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimit('api', tokens: 0);
    }

    public function testInvalidTokensErrorNamesTheExtendingAttribute()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The "$tokens" argument of "Symfony\\Component\\HttpKernel\\Tests\\Attribute\\ExtendedRateLimit" must be greater than 0, "0" given.');

        new ExtendedRateLimit(0);
    }

    public function testMethodsAreNormalized()
    {
        $rl = new RateLimit('api', methods: ['get', 'post']);
        $this->assertSame(['GET', 'POST', 'HEAD'], $rl->methods);
    }

    public function testCommaSeparatedMethods()
    {
        $this->assertSame(['GET', 'POST', 'HEAD'], (new RateLimit('api', methods: 'get, POST'))->methods);
    }
}

class ExtendedRateLimit extends RateLimit
{
    public function __construct(int $tokens)
    {
        parent::__construct('api', null, $tokens);
    }
}
