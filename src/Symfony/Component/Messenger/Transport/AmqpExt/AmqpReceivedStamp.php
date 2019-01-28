<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp applied when a message is received from Amqp.
 *
 * @experimental in 4.3
 */
class AmqpReceivedStamp implements StampInterface
{
    private $amqpEnvelope;
    private $queueName;

    public function __construct(\AMQPEnvelope $amqpEnvelope, string $queueName)
    {
        $this->amqpEnvelope = $amqpEnvelope;
        $this->queueName = $queueName;
    }

    public function getAmqpEnvelope(): \AMQPEnvelope
    {
        return $this->amqpEnvelope;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }
}
