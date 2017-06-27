<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp\Tests\RetryStrategy;

use Symfony\Component\Amqp\RetryStrategy\ExponentialRetryStrategy;
use Symfony\Component\Amqp\Tests\TestCase;

class ExponentialRetryStrategyTest extends TestCase
{
    public function testIsRetryable()
    {
        $strategy = new ExponentialRetryStrategy(3);

        $msg = $this->createMock(\AMQPEnvelope::class);
        $msg
            ->expects($this->at(0))
            ->method('getHeader')
            ->with('retries')
            ->willReturn(0)
        ;
        $msg
            ->expects($this->at(1))
            ->method('getHeader')
            ->with('retries')
            ->willReturn(1)
        ;
        $msg
            ->expects($this->at(2))
            ->method('getHeader')
            ->with('retries')
            ->willReturn(2)
        ;
        $msg
            ->expects($this->at(3))
            ->method('getHeader')
            ->with('retries')
            ->willReturn(3)
        ;
        $msg
            ->expects($this->at(4))
            ->method('getHeader')
            ->with('retries')
            ->willReturn(3)
        ;

        $this->assertTrue($strategy->isRetryable($msg));
        $this->assertTrue($strategy->isRetryable($msg));
        $this->assertTrue($strategy->isRetryable($msg));
        $this->assertFalse($strategy->isRetryable($msg));

        $this->assertSame(8, $strategy->getWaitingTime($msg));
    }
}
