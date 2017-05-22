<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp\Test;

use Symfony\Component\Amqp\UrlParser;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
trait AmqpTestTrait
{
    /**
     * @param string $body
     * @param string $queueName
     */
    private function assertNextMessageBody($body, $queueName)
    {
        $msg = $this->createQueue($queueName)->get(\AMQP_AUTOACK);

        $this->assertInstanceOf(\AMQPEnvelope::class, $msg);
        $this->assertSame($body, $msg->getBody());

        return $msg;
    }

    /**
     * @param int    $expected  The count
     * @param string $queueName
     */
    private function assertQueueSize($expected, $queueName)
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

    /**
     * @param string $name
     *
     * @return \AmqpExchange
     */
    private function createExchange($name)
    {
        $exchange = new \AmqpExchange($this->createChannel());
        $exchange->setName($name);
        $exchange->setType(\AMQP_EX_TYPE_DIRECT);
        $exchange->setFlags(\AMQP_DURABLE);
        $exchange->declareExchange();

        return $exchange;
    }

    /**
     * @param string $name
     *
     * @return \AmqpQueue
     */
    private function createQueue($name)
    {
        $queue = new \AmqpQueue($this->createChannel());
        $queue->setName($name);
        $queue->setFlags(\AMQP_DURABLE);
        $queue->declareQueue();

        return $queue;
    }

    /**
     * @param string $name
     */
    private function emptyQueue($name)
    {
        $this->createQueue($name)->purge();
    }

    /**
     * @return \AmqpChannel
     */
    private function createChannel()
    {
        return new \AmqpChannel($this->createConnection());
    }

    /**
     * @param string|null $rabbitmqUrl
     *
     * @return \AmqpConnection
     */
    private function createConnection($rabbitmqUrl = null)
    {
        $rabbitmqUrl = $rabbitmqUrl ?: getenv('RABBITMQ_URL');

        $connection = new \AmqpConnection(UrlParser::parseUrl($rabbitmqUrl));
        $connection->connect();

        return $connection;
    }
}
