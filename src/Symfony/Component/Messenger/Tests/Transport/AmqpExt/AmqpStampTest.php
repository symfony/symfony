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
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpStamp;

/**
 * @requires extension amqp
 */
class AmqpStampTest extends TestCase
{
    public function testRoutingKeyOnly()
    {
        $stamp = new AmqpStamp('routing_key');
        $this->assertSame('routing_key', $stamp->getRoutingKey());
        $this->assertSame(AMQP_NOPARAM, $stamp->getFlags());
        $this->assertSame([], $stamp->getAttributes());
    }

    public function testFlagsAndAttributes()
    {
        $stamp = new AmqpStamp(null, AMQP_DURABLE, ['delivery_mode' => 'unknown']);
        $this->assertNull($stamp->getRoutingKey());
        $this->assertSame(AMQP_DURABLE, $stamp->getFlags());
        $this->assertSame(['delivery_mode' => 'unknown'], $stamp->getAttributes());
    }

    public function testCreateFromAmqpEnvelope()
    {
        $amqpEnvelope = $this->createMock(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('routingkey');
        $amqpEnvelope->method('getDeliveryMode')->willReturn(2);
        $amqpEnvelope->method('getPriority')->willReturn(5);
        $amqpEnvelope->method('getAppId')->willReturn('appid');

        $stamp = AmqpStamp::createFromAmqpEnvelope($amqpEnvelope);

        $this->assertSame($amqpEnvelope->getRoutingKey(), $stamp->getRoutingKey());
        $this->assertSame($amqpEnvelope->getDeliveryMode(), $stamp->getAttributes()['delivery_mode']);
        $this->assertSame($amqpEnvelope->getPriority(), $stamp->getAttributes()['priority']);
        $this->assertSame($amqpEnvelope->getAppId(), $stamp->getAttributes()['app_id']);
        $this->assertSame(AMQP_NOPARAM, $stamp->getFlags());
    }

    public function testCreateFromAmqpEnvelopeWithPreviousStamp()
    {
        $amqpEnvelope = $this->createMock(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('routingkey');
        $amqpEnvelope->method('getDeliveryMode')->willReturn(2);
        $amqpEnvelope->method('getPriority')->willReturn(5);
        $amqpEnvelope->method('getAppId')->willReturn('appid');

        $previousStamp = new AmqpStamp('otherroutingkey', AMQP_MANDATORY, ['priority' => 8]);

        $stamp = AmqpStamp::createFromAmqpEnvelope($amqpEnvelope, $previousStamp);

        $this->assertSame('otherroutingkey', $stamp->getRoutingKey());
        $this->assertSame($amqpEnvelope->getDeliveryMode(), $stamp->getAttributes()['delivery_mode']);
        $this->assertSame(8, $stamp->getAttributes()['priority']);
        $this->assertSame($amqpEnvelope->getAppId(), $stamp->getAttributes()['app_id']);
        $this->assertSame(AMQP_MANDATORY, $stamp->getFlags());
    }
}
