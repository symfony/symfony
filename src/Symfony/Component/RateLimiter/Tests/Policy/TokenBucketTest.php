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
use Symfony\Component\RateLimiter\Policy\Rate;
use Symfony\Component\RateLimiter\Policy\TokenBucket;

class TokenBucketTest extends TestCase
{
    public function testBurstSizeIsCappedToWhatSerializationCanStore()
    {
        $bucket = new TokenBucket('test', \PHP_INT_MAX, Rate::perSecond(10));

        $this->assertSame(TokenBucket::MAX_BURST_SIZE, $bucket->getAvailableTokens(microtime(true)));
    }

    public function testCappedBurstSizeRoundTripsThroughSerialization()
    {
        $bucket = new TokenBucket('test', \PHP_INT_MAX, Rate::perSecond(10));
        $bucket->setTokens(TokenBucket::MAX_BURST_SIZE - 5);

        $restored = unserialize(serialize($bucket));

        $this->assertSame(TokenBucket::MAX_BURST_SIZE - 5, $restored->getAvailableTokens($restored->getTimer()));
    }
}
