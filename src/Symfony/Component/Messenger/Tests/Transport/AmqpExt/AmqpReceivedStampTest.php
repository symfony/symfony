<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpReceivedStamp;

/**
 * @requires extension amqp
 */
class AmqpReceivedStampTest extends TestCase
{
    public function testStamp()
    {
        $amqpEnvelope = $this->createMock(\AMQPEnvelope::class);

        $stamp = new AmqpReceivedStamp($amqpEnvelope, 'queueName');

        $this->assertSame($amqpEnvelope, $stamp->getAmqpEnvelope());
        $this->assertSame('queueName', $stamp->getQueueName());
    }
}
