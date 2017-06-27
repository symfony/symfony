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

// WIP

interface BrokerInterface
{
    public function connect();

    public function disconnect();

    public function isConnected(): bool;

    public function getConnection(): \AMQPConnection;

    public function getChannel(): \AMQPChannel;

    public function publish(string $routingKey, string $message, array $attributes = array()): bool;

    public function move(\AMQPEnvelope $msg, string $routingKey, array $attributes = array()): bool;

    public function moveToDeadLetter(\AMQPEnvelope $msg, array $attributes = array()): bool;

    public function consume(string $queueName, callable $callback = null, int $flags = \AMQP_NOPARAM, string $consumerTag = null);

    public function get(string $queueName, int $flags = \AMQP_NOPARAM);

    public function ack(\AMQPEnvelope $msg, int $flags = \AMQP_NOPARAM, string $queueName = null): bool;

    public function nack(\AMQPEnvelope $msg, int $flags = \AMQP_NOPARAM, string $queueName = null): bool;

    public function addQueue(\AMQPQueue $queue);

    public function hasQueue(string $queueName);

    public function getQueue(string $queueName);

    public function setQueues(array $queues);

    public function addExchange(\AMQPExchange $exchange);

    public function hasExchange(string $exchangeName);

    public function getExchange(string $exchangeName);

    public function setExchanges(array $exchanges);
}
