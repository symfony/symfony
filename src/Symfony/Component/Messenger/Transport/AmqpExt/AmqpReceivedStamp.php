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

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Stamp applied when a message is received from Amqp.
 */
class AmqpReceivedStamp implements NonSendableStampInterface
{
    private $connection;
    private $amqpEnvelope;
    private $queueName;

    public function __construct(Connection $connection, \AMQPEnvelope $amqpEnvelope, string $queueName)
    {
        $this->connection = $connection;
        $this->amqpEnvelope = $amqpEnvelope;
        $this->queueName = $queueName;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
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
