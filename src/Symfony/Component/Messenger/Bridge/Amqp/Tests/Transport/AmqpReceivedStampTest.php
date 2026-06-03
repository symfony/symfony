<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Tests\Transport;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;

#[RequiresPhpExtension('amqp')]
class AmqpReceivedStampTest extends TestCase
{
    public function testStamp()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);

        $stamp = new AmqpReceivedStamp($amqpEnvelope, 'queueName');

        $this->assertSame($amqpEnvelope, $stamp->getAmqpEnvelope());
        $this->assertSame('queueName', $stamp->getQueueName());
    }
}
