<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp;

use Symfony\Component\Amqp\Exception\UndefinedExchangeException;
use Symfony\Component\Amqp\Exception\UndefinedQueueException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SimpleBroker
{
    const DEFAULT_EXCHANGE = 'symfony.default';
    const DEAD_LETTER_EXCHANGE = 'symfony.dead_letter';
    const RETRY_EXCHANGE = 'symfony.retry';

    private $connection;
    private $channel;
    private $queues = array();
    private $exchanges = array();

    public function __construct(\AMQPConnection $connection, array $queues = array(), array $exchanges = array())
    {
        $this->connection = $connection;
        $this->connection->setReadTimeout(4 * 60 * 60);
        $this->setQueues($queues);
        $this->setExchanges($exchanges);
    }

    public static function createWithDsn(string $dsn = 'amqp://guest:guest@localhost:5672/', array $queues, array $exchanges): self
    {
        $connection = new \AMQPConnection(DsnParser::parseDsn($dsn));

        return new self($connection, $queues, $exchanges);
    }

    public function connect()
    {
        if (!$this->connection->isConnected()) {
            $this->connection->connect();
        }

        if (!$this->channel) {
            $this->channel = new \AMQPChannel($this->connection);
        }
    }

    public function disconnect()
    {
        $this->channel = null;

        if ($this->connection->isConnected()) {
            $this->connection->disconnect();
        }
    }

    public function isConnected(): bool
    {
        return $this->connection->isConnected();
    }

    public function getConnection(): \AMQPConnection
    {
        return $this->connection;
    }

    public function getChannel(): \AMQPChannel
    {
        if (null === $this->channel) {
            $this->connect();
        }

        return $this->channel;
    }

    /**
     * Publishes a new message.
     *
     * Special attributes:
     *
     *  * flags: if set, will be used during the Exchange::publish call
     *  * exchange: The exchange name to use ("symfony.default" by default)
     */
    public function publish(string $routingKey, string $message, array $attributes = array()): bool
    {
        $flags = $attributes['flags'] ?? \AMQP_MANDATORY;
        unset($attributes['flags']);

        $exchangeName = $attributes['exchange'] ?? self::DEFAULT_EXCHANGE;
        unset($attributes['exchange']);

        return $this->getExchange($exchangeName)->publish($message, $routingKey, $flags, $attributes);
    }

    /**
     * Moves a message to a given route.
     *
     * If attributes are given as third argument they will override the
     * message ones.
     */
    public function move(\AMQPEnvelope $msg, string $routingKey, array $attributes = array()): bool
    {
        static $map = array(
            'app_id' => 'getAppId',
            'content_encoding' => 'getContentEncoding',
            'content_type' => 'getContentType',
            'delivery_mode' => 'getDeliveryMode',
            'expiration' => 'getExpiration',
            'headers' => 'getHeaders',
            'message_id' => 'getMessageId',
            'priority' => 'getPriority',
            'reply_to' => 'getReplyTo',
            'timestamp' => 'getTimestamp',
            'type' => 'getType',
            'user_id' => 'getUserId',
            'exchange' => null,
        );

        $originalAttributes = array();

        foreach ($map as $key => $method) {
            if (isset($attributes[$key])) {
                $originalAttributes[$key] = $attributes[$key];

                continue;
            }

            if (!$method) {
                continue;
            }

            $value = $msg->{$method}();
            if ($value) {
                $originalAttributes[$key] = $value;
            }
        }

        return $this->publish($routingKey, $msg->getBody(), $originalAttributes);
    }

    public function moveToDeadLetter(\AMQPEnvelope $msg, array $attributes = array()): bool
    {
        return $this->move($msg, $msg->getRoutingKey().'.dead', $attributes);
    }

    public function consume(string $queueName, callable $callback = null, int $flags = \AMQP_NOPARAM, string $consumerTag = null)
    {
        $this->getQueue($queueName)->consume($callback, $flags, $consumerTag);
    }

    /**
     * Gets an Envelope from a Queue by its given name.
     *
     * @return \AMQPEnvelope|bool An enveloppe or false
     */
    public function get(string $queueName, int $flags = \AMQP_NOPARAM)
    {
        return $this->getQueue($queueName)->get($flags);
    }

    /**
     * Ack a message.
     *
     * WARNING: This shortcut only works when using the conventions
     * where the queue and the routing queue have the same name.
     *
     * If it's not the case, you MUST specify the queueName.
     */
    public function ack(\AMQPEnvelope $msg, int $flags = \AMQP_NOPARAM, string $queueName = null): bool
    {
        $queue = $this->getQueue($queueName ?: $msg->getRoutingKey());

        return $queue->ack($msg->getDeliveryTag(), $flags);
    }

    /**
     * Nack a message.
     *
     * WARNING: This shortcut only works when using the conventions
     * where the queue and the routing queue have the same name.
     *
     * If it's not the case, you MUST specify the queueName.
     */
    public function nack(\AMQPEnvelope $msg, int $flags = \AMQP_NOPARAM, string $queueName = null): bool
    {
        $queue = $this->getQueue($queueName ?: $msg->getRoutingKey());

        return $queue->nack($msg->getDeliveryTag(), $flags);
    }

    public function addQueue(\AMQPQueue $queue)
    {
        $this->queues[$queue->getName()] = $queue;
    }

    public function hasQueue(string $queueName)
    {
        return (bool) ($this->queues[$queueName] ?? false);
    }

    public function getQueue(string $queueName)
    {
        $queue = $this->queues[$queueName] ?? false;

        if (!$queue) {
            throw new UndefinedQueueException(sprintf('The queue "%s" does not exist.', $queueName));
        }

        return $queue;
    }

    public function setQueues(array $queues)
    {
        foreach ($queues as $queue) {
            $this->addQueue($queue);
        }
    }

    public function addExchange(\AMQPExchange $exchange)
    {
        $this->exchanges[$exchange->getName()] = $exchange;
    }

    public function hasExchange(string $exchangeName)
    {
        return (bool) ($this->exchanges[$exchangeName] ?? false);
    }

    public function getExchange(string $exchangeName)
    {
        $exchange = $this->exchanges[$exchangeName] ?? false;

        if (!$exchange) {
            throw new UndefinedExchangeException(sprintf('The exchange "%s" does not exist.', $exchangeName));
        }

        return $exchange;
    }

    public function setExchanges(array $exchanges)
    {
        foreach ($exchanges as $exchange) {
            $this->addExchange($exchange);
        }
    }
}
