<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp\Tests;

use Symfony\Component\Amqp\DsnParser;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
trait AmqpTestTrait
{
    private function assertNextMessageBody(string $body, string $queueName)
    {
        $msg = $this->createQueue($queueName)->get(\AMQP_AUTOACK);

        $this->assertInstanceOf(\AMQPEnvelope::class, $msg);
        $this->assertSame($body, $msg->getBody());

        return $msg;
    }

    private function assertQueueSize(int $expected, string $queueName)
    {
        $queue = $this->createQueue($queueName);

        $msgs = array();
        while (false !== $msg = $queue->get()) {
            $msgs[] = $msg;
        }

        foreach ($msgs as $msg) {
            $queue->nack($msg->getDeliveryTag(), \AMQP_REQUEUE);
        }

        $this->assertSame($expected, count($msgs));
    }

    private function createExchange(string $exchangeName): \AMQPExchange
    {
        $exchange = new \AMQPExchange($this->createChannel());
        $exchange->setName($exchangeName);
        $exchange->setType(\AMQP_EX_TYPE_DIRECT);
        $exchange->setFlags(\AMQP_DURABLE);
        $exchange->declareExchange();

        return $exchange;
    }

    private function createQueue(string $queueName): \AMQPQueue
    {
        $queue = new \AMQPQueue($this->createChannel());
        $queue->setName($queueName);
        $queue->setFlags(\AMQP_DURABLE);
        $queue->declareQueue();

        return $queue;
    }

    private function emptyQueue(string $queueName)
    {
        $this->createQueue($queueName)->purge();
    }

    private function createChannel(): \AMQPChannel
    {
        return new \AMQPChannel($this->createConnection());
    }

    private function createConnection(string $dsn = null): \AMQPConnection
    {
        $dsn = $dsn ?: getenv('AMQP_DSN');

        $connection = new \AMQPConnection(DsnParser::parseDsn($dsn));
        $connection->connect();

        return $connection;
    }
}
