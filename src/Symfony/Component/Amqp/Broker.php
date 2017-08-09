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

use Enqueue\AmqpTools\DelayStrategyAware;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\Impl\AmqpBind;
use Symfony\Component\Amqp\Exception\InvalidArgumentException;
use Symfony\Component\Amqp\Exception\LogicException;
use Symfony\Component\Amqp\Exception\NonRetryableException;
use Symfony\Component\Amqp\RetryStrategy\ConstantRetryStrategy;
use Symfony\Component\Amqp\RetryStrategy\ExponentialRetryStrategy;
use Symfony\Component\Amqp\RetryStrategy\RetryStrategyInterface;

class Broker
{
    const DEFAULT_EXCHANGE = 'symfony.default';
    const DEAD_LETTER_EXCHANGE = 'symfony.dead_letter';
    const RETRY_EXCHANGE = 'symfony.retry';

    /**
     * @var AmqpContext
     */
    private $context;

    private $queuesConfiguration = array();
    private $exchangesConfiguration = array();
    private $exchanges = array();
    private $queues = array();

    /**
     * @var AmqpConsumer[]
     */
    private $queueConsumers = array();

    /**
     * @var string[]
     */
    private $retryStrategies = array();

    /**
     * @var string[]
     */
    private $retryStrategyQueuePatterns = array();
    private $queuesBindings = array();

    private $exchangeBindings = array();

    /**
     * @param AmqpContext            $context                An AmqpContext instance
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
    public function __construct(AmqpContext $context, array $queuesConfiguration = array(), array $exchangesConfiguration = array())
    {
        $this->context = $context;

        $this->setQueuesConfiguration($queuesConfiguration);
        $this->setExchangesConfiguration($exchangesConfiguration);

        // Force the creation of this special exchange. It can not be lazy loaded as
        // it is needed for the retry workflow because all queues are bound to it.
        $this->getOrCreateExchange(self::RETRY_EXCHANGE);
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
     * Disconnects from AMQP and clears all parameters excepted configurations.
     */
    public function disconnect()
    {
        $this->context->close();
    }

    /**
     * Creates a new Exchange.
     *
     * Special arguments: See the Exchange constructor.
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return AmqpTopic
     */
    public function createExchange($name, array $arguments = array())
    {
        $topic = $this->context->createTopic($name);

        if (Broker::DEAD_LETTER_EXCHANGE === $name) {
            $topic->setType(AmqpTopic::TYPE_HEADERS);
            unset($arguments['type']);
        } elseif (Broker::RETRY_EXCHANGE === $name) {
            $topic->setType(AmqpTopic::TYPE_DIRECT);
            unset($arguments['type']);
        } elseif (isset($arguments['type'])) {
            $topic->setType($arguments['type']);
            unset($arguments['type']);
        } else {
            $topic->setType(AmqpTopic::TYPE_DIRECT);
        }

        if (isset($arguments['flags'])) {
            $topic->setFlags($arguments['flags']);
            unset($arguments['flags']);
        } else {
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);
        }

        $topic->setArguments($arguments);

        $this->context->declareTopic($topic);

        return $this->exchanges[$name] = $topic;
    }

    /**
     * @param string $name
     *
     * @return AmqpTopic
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
     * @param AmqpTopic $exchange
     */
    public function addExchange(AmqpTopic $exchange)
    {
        $this->exchanges[$exchange->getTopicName()] = $exchange;
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
     * @return AmqpQueue
     */
    public function createQueue($name, array $arguments = array(), $declare = true)
    {
        $amqpQueue = $this->context->createQueue($name);

        if (isset($arguments['exchange'])) {
            $this->getOrCreateExchange($arguments['exchange']);
        } else {
            $this->getOrCreateExchange(self::DEFAULT_EXCHANGE);
        }

        if (array_key_exists('routing_keys', $arguments)) {
            $routingKeys = $arguments['routing_keys'];
            if (is_string($routingKeys)) {
                $routingKeys = array($routingKeys);
            }
            if (!is_array($routingKeys) && null !== $routingKeys && false !== $routingKeys) {
                throw new InvalidArgumentException(sprintf('"routing_keys" option should be a string, false, null or an array of string, "%s" given.', gettype($routingKeys)));
            }

            unset($arguments['routing_keys']);
        } else {
            $routingKeys = array($name);
        }

        if (isset($arguments['flags'])) {
            $amqpQueue->setFlags($arguments['flags']);
            unset($arguments['flags']);
        } else {
            $amqpQueue->setFlags(AmqpQueue::FLAG_DURABLE);
        }

        if (isset($arguments['exchange'])) {
            $exchange = $arguments['exchange'];
            unset($arguments['exchange']);
        } else {
            $exchange = Broker::DEFAULT_EXCHANGE;
        }

        if (array_key_exists('retry_strategy', $arguments)) {
            $retryStrategy = $arguments['retry_strategy'];
            if (!$retryStrategy instanceof RetryStrategyInterface) {
                throw new InvalidArgumentException('The retry_strategy should be an instance of RetryStrategyInterface.');
            }

            $this->retryStrategies[$name] = $retryStrategy;
            unset($arguments['retry_strategy']);
        }

        if (array_key_exists('retry_strategy_queue_pattern', $arguments)) {
            $this->retryStrategyQueuePatterns[$name] = $arguments['retry_strategy_queue_pattern'];
            unset($arguments['retry_strategy_queue_pattern']);
        } else {
            $this->retryStrategyQueuePatterns[$name] = '%exchange%.%time%.wait';
        }

        if (isset($arguments['bind_arguments'])) {
            $bindArguments = $arguments['bind_arguments'];
            unset($arguments['bind_arguments']);
        } else {
            $bindArguments = array();
        }

        $amqpQueue->setArguments($arguments);

        if (null === $routingKeys) {
            $bindingConfig = [
                'queue' => $name,
                'exchange' => $exchange,
                'routing_key' => null,
                'bind_arguments' => $bindArguments,
            ];

            $this->queuesBindings[$name][] = $bindingConfig;
            $this->exchangeBindings[$exchange][] = $bindingConfig;
        } elseif (is_array($routingKeys)) {

            foreach ($routingKeys as $routingKey) {
                $bindingConfig = [
                    'queue' => $name,
                    'exchange' => $exchange,
                    'routing_key' => $routingKey,
                    'bind_arguments' => $bindArguments,
                ];

                $this->queuesBindings[$name][] = $bindingConfig;
                $this->exchangeBindings[$exchange] = $bindingConfig;
            }
        }

        // Special binding: Bind this queue, with its name as the routing key
        // with the retry exchange in order to have a nice retry workflow.
        $bindingConfig = [
            'queue' => $name,
            'exchange' => Broker::RETRY_EXCHANGE,
            'routing_key' => $name,
            'bind_arguments' => $bindArguments,
        ];
        $this->queuesBindings[$name][] = $bindingConfig;
        $this->exchangeBindings[Broker::RETRY_EXCHANGE][] = $bindingConfig;

        if ($declare) {
            $this->context->declareQueue($amqpQueue);

            foreach ($this->queuesBindings[$name] as $config) {
                $amqpTopic = $this->getExchange($config['exchange']);

                $this->context->bind(new AmqpBind(
                    $amqpTopic,
                    $amqpQueue,
                    $config['routing_key'],
                    AmqpBind::FLAG_NOPARAM,
                    $config['bind_arguments']
                ));
            }
        }

        $this->queues[$name] = $amqpQueue;

        return $amqpQueue;
    }

    /**
     * Returns a Queue for its given name.
     *
     * @param string $name
     *
     * @return AmqpQueue
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
        $amqpMessage = $this->context->createMessage($message);

        if (isset($attributes['flags'])) {
            $amqpMessage->setFlags($attributes['flags']);

            unset($attributes['flags']);
        } else {
            $amqpMessage->addFlag(AmqpMessage::FLAG_MANDATORY);
        }

        if (isset($attributes['exchange'])) {
            $exchangeName = $attributes['exchange'];
            unset($attributes['exchange']);
        } else {
            $exchangeName = self::DEFAULT_EXCHANGE;
        }

        if (isset($attributes['headers'])) {
            $amqpMessage->setProperties($attributes['headers']);
            unset($attributes['headers']);
        }

        $amqpMessage->setHeaders($attributes);

        // Force Exchange creation if needed
        $topic = $this->getOrCreateExchange($exchangeName);

        // Force Queue creation if needed
        if ($this->shouldCreateQueue($topic, $routingKey)) {
            $this->lazyLoadQueues($topic, $routingKey);
        }

        $amqpMessage->setRoutingKey($routingKey);
        $amqpMessage->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);

        $producer = $this->context->createProducer();

        if (isset($attributes['delay']) && $producer instanceof DelayStrategyAware) {
            $producer
                ->setDelayStrategy(new RabbitMqDlxDelayStrategy())
                ->setDeliveryDelay($attributes['delay'] * 1000)
            ;
        }

        $producer->send($topic, $amqpMessage);

        return true;
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
        $attributes['delay'] = $delay;

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
    public function consume($name, $callback = null, $flags = AmqpConsumer::FLAG_NOPARAM, $consumerTag = null)
    {
        $consumer = $this->getQueueConsumer($name);
        $consumer->setConsumerTag($consumerTag);
        $consumer->setFlags($flags);

        while (true) {
            if ($message = $consumer->receive(1000)) {
                if (false === call_user_func($callback, $message, $consumer)) {
                    return;
                }
            }
        }
    }

    /**
     * Gets an Envelope from a Queue by its given name.
     *
     * @param string $name  The queue name
     * @param int    $flags
     *
     * @return AmqpMessage|null
     */
    public function get($name, $flags = AmqpConsumer::FLAG_NOPARAM)
    {
        $consumer = $this->getQueueConsumer($name);
        $consumer->setFlags($flags);

        return $consumer->receiveNoWait();
    }

    /**
     * WARNING: This shortcut only works when using the conventions
     * where the queue and the routing queue have the same name.
     *
     * If it's not the case, you MUST specify the queueName.
     *
     * @param AmqpMessage $message
     * @param string|null $queueName
     *
     * @return bool
     */
    public function ack(AmqpMessage $message, $queueName = null)
    {
        $queueName = $queueName ?: $message->getRoutingKey();

        $this->getQueueConsumer($queueName)->acknowledge($message);
    }

    /**
     * WARNING: This shortcut only works when using the conventions
     * where the queue and the routing queue have the same name.
     *
     * If it's not the case, you MUST specify the queueName.
     *
     * @param AmqpMessage $message
     * @param string|null $queueName
     *
     * @return bool
     */
    public function nack(AmqpMessage $message, $queueName = null, $requeue = false)
    {
        $queueName = $queueName ?: $message->getRoutingKey();

        $this->getQueueConsumer($queueName)->reject($message, $requeue);
    }

    /**
     * WARNING: This shortcut only works when using the conventions
     * where the queue and the routing queue have the same name.
     *
     * If it's not the case, you MUST specify the queueName.
     *
     * @param AmqpMessage $amqpMessage
     * @param string|null $queueName
     * @param string|null $retryMessage
     *
     * @return bool
     */
    public function retry(AmqpMessage $amqpMessage, $queueName = null, $retryMessage = null)
    {
        $queueName = $queueName ?: $amqpMessage->getRoutingKey();

        if (!$this->hasRetryStrategy($queueName)) {
            throw new LogicException(sprintf('The queue "%s" has no retry strategy.', $queueName));
        }

        $retryStrategy = $this->retryStrategies[$queueName];

        if (!$retryStrategy->isRetryable($amqpMessage)) {
            throw new NonRetryableException($retryStrategy, $amqpMessage);
        }

        $time = $retryStrategy->getWaitingTime($amqpMessage);

        $this->createDelayedQueue($queueName, $time);

        // Copy previous headers, but omit x-death
        $headers = $amqpMessage->getHeaders();
        unset($headers['x-death']);
        $headers['queue-time'] = (string) $time;
        $headers['exchange'] = (string) self::RETRY_EXCHANGE;
        $headers['retries'] = $amqpMessage->getHeader('retries') + 1;

        // Some RabbitMQ versions fail when $message is null
        // + if a message already exists, we want to keep it.
        if (null !== $retryMessage) {
            $headers['retry-message'] = $retryMessage;
        }

        return $this->publish($queueName, $amqpMessage->getBody(), array(
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
     * @param AmqpMessage $msg
     * @param string      $routingKey
     * @param array       $attributes
     *
     * @return bool
     */
    public function move(AmqpMessage $msg, $routingKey, $attributes)
    {
        $attributes = array_replace($msg->getHeaders(), $attributes);

        return $this->publish($routingKey, $msg->getBody(), $attributes);
    }

    /**
     * @param AmqpMessage $msg
     * @param array       $attributes
     *
     * @return bool
     */
    public function moveToDeadLetter(AmqpMessage $msg, array $attributes = array())
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
     * @return AmqpTopic
     */
    private function getOrCreateExchange($name, $type = AmqpTopic::TYPE_DIRECT)
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
     * @return AmqpTopic
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
     * @return AmqpQueue
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

    private function shouldCreateQueue(AmqpTopic $topic, $routingKey)
    {
        if (AmqpTopic::TYPE_DIRECT === $topic->getType() && null === $routingKey) {
            return false;
        }

        $topicName = $topic->getTopicName();

        if ($topicName === self::DEAD_LETTER_EXCHANGE) {
            return false;
        }

        if ($topicName === self::RETRY_EXCHANGE) {
            return false;
        }

        return true;
    }

    private function lazyLoadQueues(AmqpTopic $amqpTopic, $routingKey)
    {
        // TODO find out what it does and implement this

//        $match = false;
//        $exchangeName = $amqpTopic->getTopicName();
//
//        // A queue is already setup
////        if (isset($this->queuesBindings[$exchangeName][$routingKey])) {
////            $match = true;
////        }
//
//        // Try to find a queue which is already configured
//        foreach ($this->exchangeBindings[$exchangeName] as $index => $config) {
//            if (isset($config['configured'])) {
//                $match = true;
//                continue;
//            }
//
//            if ($config['routing_key'] != $routingKey) {
//                continue;
//            }
//
//            $queue = $this->createQueueFromConfiguration($this->queuesConfiguration[$config['queue']], false);
//            $this->queues[$queue->getQueueName()] = $queue;
//
//
//
//            // Can only lazy load direct queue
//            if (AmqpTopic::TYPE_DIRECT !== $amqpTopic->getType()) {
//                $match = true;
//                $this->context->declareQueue($queue);
//                $this->exchangeBindings[$exchangeName][$index]['configured'] = true;
//
//                continue;
//            }
//
//            foreach ($bindings as $binding) {
//                if ($routingKey === $binding['routing_key']) {
//                    $match = true;
//                    $queue->declareAndBind();
//                    $this->queuesConfiguration[$name]['configured'] = true;
//                    $this->addQueue($queue);
//                }
//            }
//        }
//
//        if (!$match) {
//            $this->createQueue($routingKey, array('exchange' => $exchangeName));
//        }
    }

    /**
     * @param string $name
     *
     * @return AmqpConsumer
     */
    private function getQueueConsumer($name)
    {
        if (false == isset($this->queueConsumers[$name])) {
            $this->queueConsumers = $this->context->createConsumer($this->getQueue($name));
        }

        return $this->queueConsumers[$name];

    }
}
