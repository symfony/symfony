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
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\Limit;

class LimitTest extends TestCase
{
    public function testEnsureAcceptedDoesNotThrowExceptionIfAccepted()
    {
        $limit = new Limit(10, new \DateTimeImmutable(), true);

        $this->assertSame($limit, $limit->ensureAccepted());
    }

    public function testEnsureAcceptedThrowsRateLimitExceptionIfNotAccepted()
    {
        $limit = new Limit(10, $retryAfter = new \DateTimeImmutable(), false);

        try {
            $limit->ensureAccepted();
        } catch (RateLimitExceededException $exception) {
            $this->assertSame($limit, $exception->getLimit());
            $this->assertSame(10, $exception->getRemainingTokens());
            $this->assertSame($retryAfter, $exception->getRetryAfter());

            return;
        }

        $this->fail('RateLimitExceededException not thrown.');
    }
}
