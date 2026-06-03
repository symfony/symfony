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

class RateLimitTest extends TestCase
{
    public function testDefaults()
    {
        $rl = new RateLimit('api');

        $this->assertSame('api', $rl->limiter);
        $this->assertNull($rl->key);
        $this->assertSame(1, $rl->tokens);
        $this->assertSame([], $rl->methods);
    }

    public function testTokensMustBePositive()
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimit('api', tokens: 0);
    }

    public function testMethodsAreNormalized()
    {
        $rl = new RateLimit('api', methods: ['get', 'post']);
        $this->assertSame(['GET', 'POST', 'HEAD'], $rl->methods);
    }
}
