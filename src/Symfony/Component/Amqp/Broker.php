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

use Symfony\Component\Amqp\Exception\InvalidArgumentException;
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

    private $connection;
    private $channel;
    private $queuesConfiguration = array();
    private $exchangesConfiguration = array();
    private $exchanges = array();
    private $queues = array();
    private $retryStrategies = array();
    private $retryStrategyQueuePatterns = array();
    private $queuesBindings = array();

    /**
     * @param \AMQPConnection|string $connection             An \AMQPConnection instance or a DSN
     * @param array                  $queuesConfiguration    A collection of queue configurations
     * @param array                  $exchangesConfiguration A collection of exchange configurations
     *
     * example of $queuesConfiguration:
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
     * example of $exchangesConfiguration:
     * array(
     *     array(
     *         'name' => 'fanout'
     *         'arguments' => array(), // array, passed to Exchange constructor
     *     )
     * )
     */
    public function __construct($connection = 'amqp://guest:guest@localhost:5672/', array $queuesConfiguration = array(), array $exchangesConfiguration = array())
    {
        if (!extension_loaded('amqp')) {
            throw new \RuntimeException('The amqp extension is mandatory.');
        }

        if (is_string($connection)) {
            $connection = new \AMQPConnection(UrlParser::parseUrl($connection));
        }
        if (!$connection instanceof \AMQPConnection) {
            throw new InvalidArgumentException('The connection should be a DSN or an instance of AMQPConnection.');
        }

        $this->connection = $connection;
        $this->connection->setReadTimeout(4 * 60 * 60); // 4 hours

        $this->setQueuesConfiguration($queuesConfiguration);
        $this->setExchangesConfiguration($exchangesConfiguration);
    }

    /**
     * Returns arrays of configuration by queue name.
     *
     * @return array[]
     */
    public function getQueuesConfiguration()
    {
        return $this->queuesConfiguration;
    }

    /**
     * Connects to the AMQP using the given channel or by creating one.
     *
     * @param \AMQPChannel|null $channel
     */
    public function connect(\AMQPChannel $channel = null)
    {
        if (!$this->connection->isConnected()) {
            $this->connection->connect();
        }

        if (!$this->channel) {
            $this->channel = $channel ?: new \AMQPChannel($this->connection);
        }

        // Force the creation of this special exchange. It can not be lazy loaded as
        // it is needed for the retry workflow because all queues are bound to it.
        $this->getOrCreateExchange(self::RETRY_EXCHANGE);
    }

    /**
     * Disconnects from AMQP and clears all parameters excepted configurations.
     */
    public function disconnect()
    {
        $this->channel = null;

        if ($this->connection->isConnected()) {
            $this->connection->disconnect();
        }

        $this->queues = array();
        $this->exchanges = array();
        $this->retryStrategies = array();
        $this->retryStrategyQueuePatterns = array();
        $this->queuesBindings = array();
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        return $this->connection->isConnected();
    }

    /**
     * @return \AMQPConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return \AMQPChannel
     */
    public function getChannel()
    {
        if (null === $this->channel) {
            $this->connect();
        }

        return $this->channel;
    }

    /**
     * Creates a new Exchange.
     *
     * Special arguments: See the Exchange constructor.
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return Exchange
     */
    public function createExchange($name, array $arguments = array())
    {
        return $this->exchanges[$name] = new Exchange($this->getChannel(), $name, $arguments);
    }

    /**
     * @param string $name
     *
     * @return \AMQPExchange
     */
    public function getExchange($name)
    {
        if (!isset($this->exchanges[$name])) {
            if (!isset($this->exchangesConfiguration[$name])) {
                throw new InvalidArgumentException(sprintf('Exchange "%s" does not exist.', $name));
            }
            $this->createExchangeFromConfiguration($this->exchangesConfiguration[$name]);
        }

        return $this->exchanges[$name];
    }

    /**
     * Sets or replaces the given exchange if its name is already known.
     *
     * @param \AMQPExchange $exchange
     */
    public function addExchange(\AMQPExchange $exchange)
    {
        $this->exchanges[$exchange->getName()] = $exchange;
    }

    /**
     * Creates a new Queue.
     *
     * Special arguments: See the Queue constructor.
     *
     * @param string $name      Queue name
     * @param array  $arguments Queue constructor arguments
     * @param bool   $declare   True by default, the Queue will be bound to the current broker
     *
     * @return Queue
     */
    public function createQueue($name, array $arguments = array(), $declareAndBind = true)
    {
        if (!$declareAndBind) {
            return new Queue($this->getChannel(), $name, $arguments, $declareAndBind);
        }

        if (isset($arguments['exchange'])) {
            $this->getOrCreateExchange($arguments['exchange']);
        } else {
            $this->getOrCreateExchange(self::DEFAULT_EXCHANGE);
        }

        $queue = new Queue($this->getChannel(), $name, $arguments, $declareAndBind);

        $this->addQueue($queue);

        return $queue;
    }

    /**
     * Returns a Queue for its given name.
     *
     * @param string $name
     *
     * @return Queue
     */
    public function getQueue($name)
    {
        if (!isset($this->queues[$name])) {
            if (!isset($this->queuesConfiguration[$name])) {
                throw new InvalidArgumentException(sprintf('Queue "%s" does not exist.', $name));
            }
            $this->createQueueFromConfiguration($this->queuesConfiguration[$name]);
        }

        return $this->queues[$name];
    }

    /**
     * Binds a Queue and its strategy.
     *
     * A Queue can only be bound through unique pairs of Exchange
     * and routing key.
     *
     * @param Queue $queue
     */
    public function addQueue(Queue $queue)
    {
        $name = $queue->getName();

        $this->queues[$name] = $queue;

        $this->retryStrategies[$name] = $queue->getRetryStrategy();
        $this->retryStrategyQueuePatterns[$name] = $queue->getRetryStrategyQueuePattern();

        // We register the binding to not create queue in case of multiple
        // queues bound with the same routing key
        foreach ($queue->getBindings() as $exchange => $bindings) {
            foreach ($bindings as $binding) {
                $this->queuesBindings[$exchange][$binding['routing_key']] = true;
            }
        }
    }

    /**
     * Returns whether a Queue has a retry strategy or not.
     *
     * @param string $queueName
     *
     * @return bool
     */
    public function hasRetryStrategy($queueName)
    {
        return isset($this->retryStrategies[$queueName]);
    }

    /**
     * Publishes a new message.
     *
     * Special attributes:
     *
     *  * flags: if set, will be used during the Exchange::publish call
     *  * exchange: The exchange name to use ("symfony.default" by default)
     *
     * @param string $routingKey
     * @param string $message
     * @param array  $attributes
     *
     * @return bool True is the message was published, false otherwise
     */
    public function publish($routingKey, $message, array $attributes = array())
    {
        if (isset($attributes['flags'])) {
            $flags = $attributes['flags'];
            unset($attributes['flags']);
        } else {
            $flags = \AMQP_MANDATORY;
        }

        if (isset($attributes['exchange'])) {
            $exchangeName = $attributes['exchange'];
            unset($attributes['exchange']);
        } else {
            $exchangeName = self::DEFAULT_EXCHANGE;
        }

        // Force Exchange creation if needed
        $exchange = $this->getOrCreateExchange($exchangeName);

        // Force Queue creation if needed
        if ($this->shouldCreateQueue($exchange, $routingKey)) {
            $this->lazyLoadQueues($exchange, $routingKey);
        }

        return $exchange->publish($message, $routingKey, $flags, $attributes);
    }

    /**
     * Sends a message with delay.
     *
     * The message is stored in a pending queue before it's in the expected
     * target.
     *
     * If the target queue is not created, it will be created with default
     * configuration.
     *
     * @param string $routingKey
     * @param string $message
     * @param int    $delay      Delay in seconds
     * @param array  $attributes See the publish method
     *
     * @return bool
     */
    public function delay($routingKey, $message, $delay, array $attributes = array())
    {
        $exchangeName = isset($attributes['exchange']) ? $attributes['exchange'] : self::DEFAULT_EXCHANGE;

        $this->createDelayedQueue($routingKey, $delay, $exchangeName);

        $attributes['exchange'] = self::DEAD_LETTER_EXCHANGE;
        $attributes['headers']['queue-time'] = (string) $delay;
        $attributes['headers']['exchange'] = (string) $exchangeName;

        return $this->publish($routingKey, $message, $attributes);
    }

    /**
     * Consumes a Queue for its given name.
     *
     * @param string        $name
     * @param callable|null $callback
     * @param int           $flags
     * @param string|null   $consumerTag
     */
    public function consume($name, callable $callback = null, $flags = \AMQP_NOPARAM, $consumerTag = null)
    {
        $this->getOrCreateQueue($name)->consume($callback, $flags, $consumerTag);
    }

    /**
     * Gets an Envelope from a Queue by its given name.
     *
     * @param string $name  The queue name
     * @param int    $flags
     *
     * @return \AMQPEnvelope|bool An enveloppe or false
     */
    public function get($name, $flags = \AMQP_NOPARAM)
    {
        return $this->getOrCreateQueue($name)->get($flags);
    }

    /**
     * WARNING: This shortcut only works when using the conventions
     * where the queue and the routing queue have the same name.
     *
     * If it's not the case, you MUST specify the queueName.
     *
     * @param \AMQPEnvelope $msg
     * @param int           $flags
     * @param string|null   $queueName
     *
     * @return bool
     */
    public function ack(\AMQPEnvelope $msg, $flags = \AMQP_NOPARAM, $queueName = null)
    {
        $queueName = $queueName ?: $msg->getRoutingKey();

        return $this->getQueue($queueName)->ack($msg->getDeliveryTag(), $flags);
    }

    /**
     * WARNING: This shortcut only works when using the conventions
     * where the queue and the routing queue have the same name.
     *
     * If it's not the case, you MUST specify the queueName.
     *
     * @param \AMQPEnvelope $msg
     * @param int           $flags
     * @param string|null   $queueName
     *
     * @return bool
     */
    public function nack(\AMQPEnvelope $msg, $flags = \AMQP_NOPARAM, $queueName = null)
    {
        $queueName = $queueName ?: $msg->getRoutingKey();

        return $this->getQueue($queueName)->nack($msg->getDeliveryTag(), $flags);
    }

    /**
     * WARNING: This shortcut only works when using the conventions
     * where the queue and the routing queue have the same name.
     *
     * If it's not the case, you MUST specify the queueName.
     *
     * @param \AMQPEnvelope $msg
     * @param string|null   $queueName
     * @param string|null   $message
     *
     * @return bool
     */
    public function retry(\AMQPEnvelope $msg, $queueName = null, $message = null)
    {
        $queueName = $queueName ?: $msg->getRoutingKey();

        if (!$this->hasRetryStrategy($queueName)) {
            throw new LogicException(sprintf('The queue "%s" has no retry strategy.', $queueName));
        }

        $retryStrategy = $this->retryStrategies[$queueName];

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

        // Some RabbitMQ versions fail when $message is null
        // + if a message already exists, we want to keep it.
        if (null !== $message) {
            $headers['retry-message'] = $message;
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
     *
     * @param \AMQPEnvelope $msg
     * @param string        $routingKey
     * @param array         $attributes
     *
     * @return bool
     */
    public function move(\AMQPEnvelope $msg, $routingKey, array $attributes = array())
    {
        $map = array(
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
        );

        $originalAttributes = array();

        foreach ($map as $key => $method) {
            if (isset($attributes[$key])) {
                $originalAttributes[$key] = $attributes[$key];

                continue;
            }

            $value = $msg->{$method}();
            if ($value) {
                $originalAttributes[$key] = $value;
            }
        }

        return $this->publish($routingKey, $msg->getBody(), $originalAttributes);
    }

    /**
     * @param \AMQPEnvelope $msg
     * @param array         $attributes
     *
     * @return bool
     */
    public function moveToDeadLetter(\AMQPEnvelope $msg, array $attributes = array())
    {
        return $this->move($msg, $msg->getRoutingKey().'.dead', $attributes);
    }

    private function setQueuesConfiguration(array $queuesConfiguration)
    {
        $defaultQueueConfiguration = array(
            'arguments' => array(),
            'retry_strategy' => null,
            'retry_strategy_options' => array(),
            'thresholds' => array('warning' => null, 'critical' => null),
        );

        foreach ($queuesConfiguration as $configuration) {
            if (!isset($configuration['name'])) {
                throw new InvalidArgumentException('The key "name" is required to configure a Queue.');
            }

            if (isset($this->queuesConfiguration[$configuration['name']])) {
                throw new InvalidArgumentException(sprintf('A queue named "%s" already exists.', $configuration['name']));
            }

            $configuration = array_replace_recursive($defaultQueueConfiguration, $configuration);

            $this->queuesConfiguration[$configuration['name']] = $configuration;
        }
    }

    private function setExchangesConfiguration(array $exchangesConfiguration)
    {
        $defaultExchangeConfiguration = array(
            'arguments' => array(),
        );

        foreach ($exchangesConfiguration as $configuration) {
            if (!isset($configuration['name'])) {
                throw new InvalidArgumentException('The key "name" is required to configure an Exchange.');
            }

            if (isset($this->exchangesConfiguration[$configuration['name']])) {
                throw new InvalidArgumentException(sprintf('An exchange named "%s" already exists.', $configuration['name']));
            }

            $configuration = array_replace_recursive($defaultExchangeConfiguration, $configuration);

            $this->exchangesConfiguration[$configuration['name']] = $configuration;
        }
    }

    /**
     * @param string $name
     * @param string $type
     *
     * @return \AMQPExchange
     */
    private function getOrCreateExchange($name, $type = \AMQP_EX_TYPE_DIRECT)
    {
        if (!isset($this->exchanges[$name])) {
            if (isset($this->exchangesConfiguration[$name])) {
                $this->createExchangeFromConfiguration($this->exchangesConfiguration[$name]);
            } else {
                $this->createExchange($name, array('type' => $type));
            }
        }

        return $this->exchanges[$name];
    }

    /**
     * @param array $conf
     *
     * @return Exchange
     */
    private function createExchangeFromConfiguration(array $conf)
    {
        return $this->createExchange($conf['name'], $conf['arguments']);
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return Queue
     */
    private function getOrCreateQueue($name, array $arguments = array())
    {
        if (!isset($this->queues[$name])) {
            if (isset($this->queuesConfiguration[$name])) {
                $this->createQueueFromConfiguration($this->queuesConfiguration[$name]);
            } else {
                $this->createQueue($name, $arguments);
            }
        }

        return $this->queues[$name];
    }

    /**
     * @param array $conf
     * @param bool  $declareAndBind
     *
     * @return Queue
     */
    private function createQueueFromConfiguration(array $conf, $declareAndBind = true)
    {
        $args = $conf['arguments'];

        if ('constant' === $conf['retry_strategy']) {
            $args['retry_strategy'] = new ConstantRetryStrategy($conf['retry_strategy_options']['time'], $conf['retry_strategy_options']['max']);
        } elseif ('exponential' === $conf['retry_strategy']) {
            $args['retry_strategy'] = new ExponentialRetryStrategy($conf['retry_strategy_options']['max'], $conf['retry_strategy_options']['offset']);
        }

        return $this->createQueue($conf['name'], $args, $declareAndBind);
    }

    /**
     * @param string      $name
     * @param int         $time
     * @param string|null $originalExchange
     */
    private function createDelayedQueue($name, $time, $originalExchange = null)
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
                isset($this->retryStrategyQueuePatterns[$name]) ? $this->retryStrategyQueuePatterns[$name] : '%exchange%.%time%.wait'
            );
        }

        if (isset($this->queues[$retryRoutingKey])) {
            return;
        }

        // Force Exchange creation if needed
        $this->getOrCreateExchange(self::DEAD_LETTER_EXCHANGE);

        // Force retry Queue creation if needed
        $this->getOrCreateQueue($retryRoutingKey, array(
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

    private function shouldCreateQueue(\AMQPExchange $exchange, $routingKey)
    {
        if (\AMQP_EX_TYPE_DIRECT === $exchange->getType() && null === $routingKey) {
            return false;
        }

        $exchangeName = $exchange->getName();

        if ($exchangeName === self::DEAD_LETTER_EXCHANGE) {
            return false;
        }

        if ($exchangeName === self::RETRY_EXCHANGE) {
            return false;
        }

        return true;
    }

    private function lazyLoadQueues(\AMQPExchange $exchange, $routingKey)
    {
        $match = false;
        $exchangeName = $exchange->getName();

        // A queue is already setup
        if (isset($this->queuesBindings[$exchangeName][$routingKey])) {
            $match = true;
        }

        // Try to find a queue which is already configured
        foreach ($this->queuesConfiguration as $name => $config) {
            if (isset($config['configured'])) {
                $match = true;
                continue;
            }

            $queue = $this->createQueueFromConfiguration($config, false);

            foreach ($queue->getBindings() as $ex => $bindings) {
                if ($ex !== $exchangeName) {
                    continue;
                }

                // Can only lazy load direct queue
                if (\AMQP_EX_TYPE_DIRECT !== $exchange->getType()) {
                    $match = true;
                    $queue->declareAndBind();
                    $this->queuesConfiguration[$name]['configured'] = true;
                    $this->addQueue($queue);

                    continue;
                }

                foreach ($bindings as $binding) {
                    if ($routingKey === $binding['routing_key']) {
                        $match = true;
                        $queue->declareAndBind();
                        $this->queuesConfiguration[$name]['configured'] = true;
                        $this->addQueue($queue);
                    }
                }
            }
        }

        if (!$match) {
            $this->createQueue($routingKey, array('exchange' => $exchangeName));
        }
    }
}
