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
use Symfony\Component\Messenger\Retry\DynamicRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RetryStrategyStamp;

class DynamicRetryStrategyTest extends TestCase
{
    public function testIsRetryable()
    {
        $strategy = new DynamicRetryStrategy($this->createStub(RetryStrategyInterface::class));
        $envelope = new Envelope(new \stdClass(), [new RetryStrategyStamp(true)]);

        $this->assertTrue($strategy->isRetryable($envelope));
    }

    public function testIsNotRetryable()
    {
        $strategy = new DynamicRetryStrategy($this->createStub(RetryStrategyInterface::class));
        $envelope = new Envelope(new \stdClass(), [new RetryStrategyStamp(false)]);

        $this->assertFalse($strategy->isRetryable($envelope));
    }

    public function testIsRetryableWithNoStamp()
    {
        $fallbackStrategy = $this->createMock(RetryStrategyInterface::class);
        $fallbackStrategy->expects($this->once())->method('isRetryable')->willReturn(true);

        $strategy = new DynamicRetryStrategy($fallbackStrategy);
        $envelope = new Envelope(new \stdClass());

        $this->assertTrue($strategy->isRetryable($envelope));
    }

    public function testGetWaitingTime()
    {
        $strategy = new DynamicRetryStrategy($this->createStub(RetryStrategyInterface::class));
        $envelope = new Envelope(new \stdClass(), [new RetryStrategyStamp(null, 123)]);

        $this->assertSame(123, $strategy->getWaitingTime($envelope));
    }

    public function testGetWaitingTimeWithNoStamp()
    {
        $fallbackStrategy = $this->createMock(RetryStrategyInterface::class);
        $fallbackStrategy->expects($this->once())->method('getWaitingTime')->willReturn(456);

        $strategy = new DynamicRetryStrategy($fallbackStrategy);
        $envelope = new Envelope(new \stdClass());

        $this->assertSame(456, $strategy->getWaitingTime($envelope));
    }
}
