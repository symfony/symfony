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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Amqp\RetryStrategy\ConstantRetryStrategy;

class ConstantRetryStrategyTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Amqp\Exception\InvalidArgumentException
     * @expectedExceptionMessage "time" should be at least 1.
     */
    public function testConstrutorWithInvalidTime()
    {
        new ConstantRetryStrategy(0);
    }

    public function testIsRetryable()
    {
        $strategy = new ConstantRetryStrategy(2, 3);

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

        $this->assertTrue($strategy->isRetryable($msg));
        $this->assertTrue($strategy->isRetryable($msg));
        $this->assertTrue($strategy->isRetryable($msg));
        $this->assertFalse($strategy->isRetryable($msg));

        $this->assertSame(2, $strategy->getWaitingTime($msg));
    }
}
