<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Retry;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class MultiplierRetryStrategyTest extends TestCase
{
    public function testIsRetryable()
    {
        $strategy = new MultiplierRetryStrategy(3);
        $envelope = new Envelope(new \stdClass(), [new RedeliveryStamp(0)]);

        $this->assertTrue($strategy->isRetryable($envelope));
    }

    public function testIsNotRetryable()
    {
        $strategy = new MultiplierRetryStrategy(3);
        $envelope = new Envelope(new \stdClass(), [new RedeliveryStamp(3)]);

        $this->assertFalse($strategy->isRetryable($envelope));
    }

    public function testIsNotRetryableWithZeroMax()
    {
        $strategy = new MultiplierRetryStrategy(0);
        $envelope = new Envelope(new \stdClass(), [new RedeliveryStamp(0)]);
        $this->assertFalse($strategy->isRetryable($envelope));
    }

    public function testIsRetryableWithNoStamp()
    {
        $strategy = new MultiplierRetryStrategy(3);
        $envelope = new Envelope(new \stdClass());

        $this->assertTrue($strategy->isRetryable($envelope));
    }

    /**
     * @dataProvider getWaitTimeTests
     */
    public function testGetWaitTime(int $delay, int $multiplier, int $maxDelay, int $previousRetries, int $expectedDelay)
    {
        $strategy = new MultiplierRetryStrategy(10, $delay, $multiplier, $maxDelay);
        $envelope = new Envelope(new \stdClass(), [new RedeliveryStamp($previousRetries)]);

        $this->assertSame($expectedDelay, $strategy->getWaitingTime($envelope));
    }

    public function getWaitTimeTests(): iterable
    {
        // delay, multiplier, maxDelay, retries, expectedDelay
        yield [1000, 1, 5000, 0, 1000];
        yield [1000, 1, 5000, 1, 1000];
        yield [1000, 1, 5000, 2, 1000];

        yield [1000, 2, 10000, 0, 1000];
        yield [1000, 2, 10000, 1, 2000];
        yield [1000, 2, 10000, 2, 4000];
        yield [1000, 2, 10000, 3, 8000];
        yield [1000, 2, 10000, 4, 10000]; // max hit
        yield [1000, 2, 0, 4, 16000]; // no max

        yield [1000, 3, 10000, 0, 1000];
        yield [1000, 3, 10000, 1, 3000];
        yield [1000, 3, 10000, 2, 9000];

        yield [1000, 1, 500, 0, 500]; // max hit immediately

        // never a delay
        yield [0, 2, 10000, 0, 0];
        yield [0, 2, 10000, 1, 0];
    }
}
