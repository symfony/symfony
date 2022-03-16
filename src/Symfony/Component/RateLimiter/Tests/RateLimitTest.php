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
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * @group time-sensitive
 */
class RateLimitTest extends TestCase
{
    public function testEnsureAcceptedDoesNotThrowExceptionIfAccepted()
    {
        $rateLimit = new RateLimit(10, new \DateTimeImmutable(), true, 10);

        $this->assertSame($rateLimit, $rateLimit->ensureAccepted());
    }

    public function testEnsureAcceptedThrowsRateLimitExceptionIfNotAccepted()
    {
        $rateLimit = new RateLimit(10, $retryAfter = new \DateTimeImmutable(), false, 10);

        try {
            $rateLimit->ensureAccepted();
        } catch (RateLimitExceededException $exception) {
            $this->assertSame($rateLimit, $exception->getRateLimit());
            $this->assertSame(10, $exception->getRemainingTokens());
            $this->assertSame($retryAfter, $exception->getRetryAfter());

            return;
        }

        $this->fail('RateLimitExceededException not thrown.');
    }

    public function testWaitUsesMicrotime()
    {
        ClockMock::register(RateLimit::class);
        $retryAfter = time() + 2.5; // get timestamp in the middle of a second (xxx.5)
        $rateLimit = new RateLimit(10, \DateTimeImmutable::createFromFormat('U.u', $retryAfter), true, 10);

        $rateLimit->wait(); // wait until $retryAfter (~2.5 seconds)
        $this->assertEqualsWithDelta($retryAfter, microtime(true), 0.49);
    }
}
