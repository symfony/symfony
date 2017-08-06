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
use Symfony\Component\Amqp\RetryStrategy\RetryStrategyInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Queue extends \AMQPQueue
{
    private $bindings;
    private $retryStrategy;
    private $retryStrategyQueuePattern;

    /**
     * Special arguments:
     *
     * * routing_keys:
     *   * If not set, $name will be used.
     *   * If null, the bind will not use a routing key
     *   * If false, the queue will not be bound
     *   * Otherwise, the values string[] will be used
     * * flags: if set, setFlags() will be called with its value
     * * exchange: exchange to bind the queue to (default exchange is used if not set)
     * * retry_strategy: A retry strategy instance to use (see RetryStrategyInterface)
     * * retry_strategy_queue_pattern:
     *   * The queue pattern to use for messages that needs to wait (default to %exchange%.%time%.wait)
     *   * The pattern is expanded according to the %exchange% and %time% values.
     * * bind_arguments: An array of bind arguments
     *
     * @param \AMQPChannel $channel
     * @param $name
     * @param array $arguments
     * @param bool  $declare
     */
    public function __construct(\AMQPChannel $channel, $name, array $arguments = array(), $declare = true)
    {
        $this->bindings = array();

        parent::__construct($channel);

        $this->setName($name);

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
            $this->setFlags($arguments['flags']);
            unset($arguments['flags']);
        } else {
            $this->setFlags(\AMQP_DURABLE);
        }

        if (isset($arguments['exchange'])) {
            $exchange = $arguments['exchange'];
            unset($arguments['exchange']);
        } else {
            $exchange = Broker::DEFAULT_EXCHANGE;
        }

        if (array_key_exists('retry_strategy', $arguments)) {
            $this->retryStrategy = $arguments['retry_strategy'];
            if (!$this->retryStrategy instanceof RetryStrategyInterface) {
                throw new InvalidArgumentException('The retry_strategy should be an instance of RetryStrategyInterface.');
            }
            unset($arguments['retry_strategy']);
        }

        if (array_key_exists('retry_strategy_queue_pattern', $arguments)) {
            $this->retryStrategyQueuePattern = $arguments['retry_strategy_queue_pattern'];
            unset($arguments['retry_strategy_queue_pattern']);
        } else {
            $this->retryStrategyQueuePattern = '%exchange%.%time%.wait';
        }

        if (isset($arguments['bind_arguments'])) {
            $bindArguments = $arguments['bind_arguments'];
            unset($arguments['bind_arguments']);
        } else {
            $bindArguments = array();
        }

        $this->setArguments($arguments);

        if (null === $routingKeys) {
            $this->bindings[$exchange][] = array(
                'routing_key' => $routingKeys,
                'bind_arguments' => $bindArguments,
            );
        } elseif (is_array($routingKeys)) {
            foreach ($routingKeys as $routingKey) {
                $this->bindings[$exchange][] = array(
                    'routing_key' => $routingKey,
                    'bind_arguments' => $bindArguments,
                );
            }
        }

        // Special binding: Bind this queue, with its name as the routing key
        // with the retry exchange in order to have a nice retry workflow.
        $this->bindings[Broker::RETRY_EXCHANGE][] = array(
            'routing_key' => $name,
            'bind_arguments' => array(),
        );

        if ($declare) {
            $this->declareAndBind();
        }
    }

    /**
     * Declares this queue by binding it to Exchange instances.
     */
    public function declareAndBind()
    {
        $this->declareQueue();

        foreach ($this->bindings as $exchange => $configs) {
            foreach ($configs as $config) {
                parent::bind($exchange, $config['routing_key'], $config['bind_arguments']);
            }
        }
    }

    /**
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * @return RetryStrategyInterface
     */
    public function getRetryStrategy()
    {
        return $this->retryStrategy;
    }

    /**
     * @return string
     */
    public function getRetryStrategyQueuePattern()
    {
        return $this->retryStrategyQueuePattern;
    }
}
