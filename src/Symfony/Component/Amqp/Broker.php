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

use Symfony\Component\Amqp\Exception\LogicException;
use Symfony\Component\Amqp\Exception\NonRetryableException;
use Symfony\Component\Amqp\RetryStrategy\ConstantRetryStrategy;
use Symfony\Component\Amqp\RetryStrategy\ExponentialRetryStrategy;

/**
 * Provides nice shortcuts for common use cases.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Broker
{
    const DEFAULT_EXCHANGE = 'symfony.default';
    const DEAD_LETTER_EXCHANGE = 'symfony.dead_letter';
    const RETRY_EXCHANGE = 'symfony.retry';

    private $configuration;
    private $simpleBroker;
    private $queuesBindings = array();

    /**
     * Create a new Broker instance.
     *
     * Example of $queuesConfiguration
     * array(
     *     array(
     *         'name' => 'project.created',
     *         'arguments' => array(), // array, passed to Queue constructor
     *         'retry_strategy' => null, // null, 'exponential', 'constant'
     *         'retry_strategy_options' => array(), // array, passed to the Strategy constructor
     *         'thresholds' => array('warning' => null, 'critical' => null),
     *     )
     * )
     *
     * Example of $exchangesConfiguration:
     * array(
     *     array(
     *         'name' => 'fanout'
     *         'arguments' => array(), // array, passed to Exchange constructor
     *     )
     * )
     */
    public function __construct(\AMQPConnection $connection, Configuration $configuration = null)
    {
        if (!extension_loaded('amqp')) {
            throw new \RuntimeException('The amqp extension is required.');
        }

        $this->configuration = $configuration ?: new Configuration();
        $this->simpleBroker = new SimpleBroker($connection);
    }

    public static function createWithDsn(string $dsn = 'amqp://guest:guest@localhost:5672/', Configuration $configuration = null): self
    {
        $connection = new \AMQPConnection(DsnParser::parseDsn($dsn));

        return new self($connection, $configuration);
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function connect()
    {
        $this->simpleBroker->connect();

        // Force the creation of this special exchange. It can not be lazy
        // loaded as it is needed for the retry workflow because all queues are
        // bound to it.
        $this->getExchange(self::RETRY_EXCHANGE);
    }

    /**
     * Disconnects from AMQP and clears all parameters excepted configurations.
     */
    public function disconnect()
    {
        $this->simpleBroker->disconnect();

        $this->queuesBindings = array();
    }

    public function isConnected(): bool
    {
        return $this->simpleBroker->isConnected();
    }

    public function getConnection(): \AMQPConnection
    {
        return $this->simpleBroker->getConnection();
    }

    public function getChannel(): \AMQPChannel
    {
        return $this->simpleBroker->getChannel();
    }

    /**
     * Creates a new Exchange.
     *
     * Special arguments: See the Exchange constructor.
     */
    public function createExchange(string $exchangeName, array $arguments = array()): Exchange
    {
        $exchange = new Exchange($this->getChannel(), $exchangeName, $arguments);

        $this->simpleBroker->addExchange($exchange);

        return $exchange;
    }

    public function getExchange(string $exchangeName, string $type = \AMQP_EX_TYPE_DIRECT): \AMQPExchange
    {
        if (!$this->simpleBroker->hasExchange($exchangeName)) {
            $exchangeConfiguration = $this->configuration->getExchangeConfiguration($exchangeName);
            if ($exchangeConfiguration) {
                $this->createExchangeFromConfiguration($exchangeConfiguration);
            } else {
                $this->createExchange($exchangeName, array('type' => $type));
            }
        }

        return $this->simpleBroker->getExchange($exchangeName);
    }

    /**
     * Sets or replaces the given exchange if its name is already known.
     */
    public function addExchange(\AMQPExchange $exchange)
    {
        $this->simpleBroker->addExchange($exchange);
    }

    /**
     * Creates a new Queue.
     *
     * Special arguments: See the Queue constructor.
     */
    public function createQueue(string $queueName, array $arguments = array(), bool $declareAndBind = true): Queue
    {
        if (!$declareAndBind) {
            return new Queue($this->getChannel(), $queueName, $arguments, $declareAndBind);
        }

        // Force exchange creation
        $this->getExchange($arguments['exchange'] ?? self::DEFAULT_EXCHANGE);

        $queue = new Queue($this->getChannel(), $queueName, $arguments, $declareAndBind);

        $this->addQueue($queue);

        return $queue;
    }

    public function getQueue(string $queueName, array $arguments = array()): Queue
    {
        if (!$this->simpleBroker->hasQueue($queueName)) {
            $queueConfiguration = $this->configuration->getQueueConfiguration($queueName);
            if ($queueConfiguration) {
                $this->createQueueFromConfiguration($queueConfiguration);
            } else {
                $this->createQueue($queueName, $arguments);
            }
        }

        return $this->simpleBroker->getQueue($queueName);
    }

    public function hasQueue(string $queueName)
    {
        return  $this->simpleBroker->hasQueue($queueName);
    }

    public function addQueue(Queue $queue)
    {
        $this->simpleBroker->addQueue($queue);

        // We register the binding to not create queue in case of multiple
        // queues bound with the same routing key
        foreach ($queue->getBindings() as $exchange => $bindings) {
            foreach ($bindings as $binding) {
                $this->queuesBindings[$exchange][$binding['routing_key']] = true;
            }
        }
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
        // Force Exchange creation if needed
        $exchange = $this->getExchange($attributes['exchange'] ?? self::DEFAULT_EXCHANGE);

        // Force Queue creation if needed
        if ($this->shouldCreateQueue($exchange, $routingKey)) {
            $this->setupQueues($exchange, $routingKey);
        }

        return $this->simpleBroker->publish($routingKey, $message, $attributes);
    }

    /**
     * Retry the message later.
     *
     * WARNING: This shortcut only works when using the conventions
     * where the queue and the routing queue have the same name.
     *
     * If it's not the case, you MUST specify the queueName.
     */
    public function retry(\AMQPEnvelope $msg, string $queueName = null, string $retryMessage = null): bool
    {
        $queueName = $queueName ?: $msg->getRoutingKey();

        $queue = $this->getQueue($queueName);

        if (!$queue->getRetryStrategy()) {
            throw new LogicException(sprintf('The queue "%s" has no retry strategy.', $queueName));
        }

        $retryStrategy = $queue->getRetryStrategy();

        if (!$retryStrategy->isRetryable($msg)) {
            throw new NonRetryableException($retryStrategy, $msg);
        }

        $time = $retryStrategy->getWaitingTime($msg);

        $this->createDelayedQueue($queueName, $time);

        // Copy previous headers, but omit x-death
        $headers = $msg->getHeaders();
        unset($headers['x-death']);
        $headers['queue-time'] = (string) $time;
        $headers['exchange'] = (string) self::RETRY_EXCHANGE;
        $headers['retries'] = $msg->getHeader('retries') + 1;

        // Some RabbitMQ versions fail when $retryMessage is null
        if (null !== $retryMessage) {
            $headers['retry-message'] = $retryMessage;
        }

        return $this->publish($queueName, $msg->getBody(), array(
            'exchange' => self::DEAD_LETTER_EXCHANGE,
            'headers' => $headers,
        ));
    }

    /**
     * Moves a message to a given route.
     *
     * If attributes are given as third argument they will override the
     * message ones.
     */
    public function move(\AMQPEnvelope $msg, string $routingKey, array $attributes = array()): bool
    {
        return $this->simpleBroker->move($msg, $routingKey, $attributes);
    }

    public function moveToDeadLetter(\AMQPEnvelope $msg, array $attributes = array()): bool
    {
        return $this->simpleBroker->moveToDeadLetter($msg, $attributes);
    }

    /**
     * Sends a message with delay.
     *
     * The message is stored in a pending queue before it's in the expected
     * target.
     *
     * If the target queue is not created, it will be created with default
     * configuration.
     */
    public function delay(string $routingKey, string $message, int $delay, array $attributes = array()): bool
    {
        $exchangeName = $attributes['exchange'] ?? self::DEFAULT_EXCHANGE;

        $this->createDelayedQueue($routingKey, $delay, $exchangeName);

        $attributes['exchange'] = self::DEAD_LETTER_EXCHANGE;
        $attributes['headers']['queue-time'] = (string) $delay;
        $attributes['headers']['exchange'] = (string) $exchangeName;

        return $this->publish($routingKey, $message, $attributes);
    }

    public function consume(string $queueName, callable $callback = null, int $flags = \AMQP_NOPARAM, string $consumerTag = null)
    {
        $this->getQueue($queueName);

        $this->simpleBroker->consume($queueName, $callback, $flags, $consumerTag);
    }

    /**
     * Gets an Envelope from a Queue by its given name.
     *
     * @return \AMQPEnvelope|bool An enveloppe or false
     */
    public function get(string $queueName, int $flags = \AMQP_NOPARAM)
    {
        $this->getQueue($queueName);

        return $this->simpleBroker->get($queueName, $flags);
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
        $this->getQueue($queueName ?: $msg->getRoutingKey());

        return $this->simpleBroker->ack($msg, $flags, $queueName);
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
        $this->getQueue($queueName ?: $msg->getRoutingKey());

        return $this->simpleBroker->nack($msg, $flags, $queueName);
    }

    private function createExchangeFromConfiguration(array $exchangeConfiguration): Exchange
    {
        return $this->createExchange($exchangeConfiguration['name'], $exchangeConfiguration['arguments']);
    }

    private function createQueueFromConfiguration(array $exchangeConfiguration, bool $declareAndBind = true): Queue
    {
        $args = $exchangeConfiguration['arguments'];

        if ('constant' === $exchangeConfiguration['retry_strategy']) {
            $args['retry_strategy'] = new ConstantRetryStrategy($exchangeConfiguration['retry_strategy_options']['time'], $exchangeConfiguration['retry_strategy_options']['max']);
        } elseif ('exponential' === $exchangeConfiguration['retry_strategy']) {
            $args['retry_strategy'] = new ExponentialRetryStrategy($exchangeConfiguration['retry_strategy_options']['max'], $exchangeConfiguration['retry_strategy_options']['offset']);
        }

        return $this->createQueue($exchangeConfiguration['name'], $args, $declareAndBind);
    }

    private function createDelayedQueue(string $queueName, int $time, string $originalExchange = null)
    {
        if ($originalExchange) {
            $retryExchange = $originalExchange;
            $retryRoutingKey = str_replace(
                array('%exchange%', '%time%'),
                array($retryExchange, sprintf('%06d', $time)),
                '%exchange%.%time%.wait'
            );
        } else {
            $originalExchange = self::RETRY_EXCHANGE;
            $retryExchange = self::RETRY_EXCHANGE;
            $retryRoutingKey = str_replace(
                array('%exchange%', '%time%'),
                array($retryExchange, sprintf('%06d', $time)),
                $this->simpleBroker->getQueue($queueName)->getRetryStrategyQueuePattern()
            );
        }

        if ($this->simpleBroker->hasQueue($retryRoutingKey)) {
            return;
        }

        // Force Exchange creation if needed
        $this->getExchange(self::DEAD_LETTER_EXCHANGE);

        // Force retry Queue creation if needed
        $this->getQueue($retryRoutingKey, array(
            'exchange' => self::DEAD_LETTER_EXCHANGE,
            'x-message-ttl' => $time * 1000,
            'x-dead-letter-exchange' => $retryExchange,
            'bind_arguments' => array(
                'queue-time' => (string) $time,
                'exchange' => $originalExchange,
                'x-match' => 'all',
            ),
        ));
    }

    private function shouldCreateQueue(\AMQPExchange $exchange, $routingKey): bool
    {
        if (\AMQP_EX_TYPE_DIRECT === $exchange->getType() && null === $routingKey) {
            return false;
        }

        $exchangeName = $exchange->getName();

        if (self::DEAD_LETTER_EXCHANGE === $exchangeName) {
            return false;
        }

        if (self::RETRY_EXCHANGE === $exchangeName) {
            return false;
        }

        return true;
    }

    private function setupQueues(\AMQPExchange $exchange, $routingKey)
    {
        $match = false;
        $exchangeName = $exchange->getName();

        // A queue is already setup
        if (isset($this->queuesBindings[$exchangeName][$routingKey])) {
            $match = true;
        }

        // Setup all queues
        foreach ($this->configuration->getQueuesConfiguration() as $queueName => $config) {
            if ($this->simpleBroker->hasQueue($queueName)) {
                $match = true;
                continue;
            }

            $queue = $this->createQueueFromConfiguration($config, false);

            foreach ($queue->getBindings() as $configuredExchangeName => $bindings) {
                if ($configuredExchangeName !== $exchangeName) {
                    continue;
                }

                // Can only lazy load direct queue
                if (\AMQP_EX_TYPE_DIRECT !== $exchange->getType()) {
                    $match = true;
                    $queue->declareAndBind();
                    $this->addQueue($queue);

                    continue;
                }

                foreach ($bindings as $binding) {
                    if ($routingKey === $binding['routing_key']) {
                        $match = true;
                        $queue->declareAndBind();
                        $this->addQueue($queue);
                    }
                }
            }
        }

        // Not queues are currently matching, we create one to not loose the message
        if (!$match) {
            $this->createQueue($routingKey, array('exchange' => $exchangeName));
        }
    }
}
