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
}
