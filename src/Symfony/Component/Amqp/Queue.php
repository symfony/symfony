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
     * Create a new Queue.
     *
     * Special arguments:
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
     */
    public function __construct(\AMQPChannel $channel, string $name, array $arguments = array(), bool $declareAndBind = true)
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
                throw new InvalidArgumentException(sprintf('"routing_keys" option must be a string, false, null or an array of string, "%s" given.', gettype($routingKeys)));
            }

            unset($arguments['routing_keys']);
        } else {
            $routingKeys = array($name);
        }

        $this->setFlags($arguments['flags'] ?? \AMQP_DURABLE);
        unset($arguments['flags']);

        $exchange = $arguments['exchange'] ?? Broker::DEFAULT_EXCHANGE;
        unset($arguments['exchange']);

        if (array_key_exists('retry_strategy', $arguments)) {
            $this->retryStrategy = $arguments['retry_strategy'];
            if (!$this->retryStrategy instanceof RetryStrategyInterface) {
                throw new InvalidArgumentException('The retry_strategy must be an instance of RetryStrategyInterface.');
            }
            unset($arguments['retry_strategy']);
        }

        $this->retryStrategyQueuePattern = $arguments['retry_strategy_queue_pattern'] ?? '%exchange%.%time%.wait';
        unset($arguments['retry_strategy_queue_pattern']);

        $bindArguments = $arguments['bind_arguments'] ?? array();
        unset($arguments['bind_arguments']);

        $this->setArguments($arguments);

        if (null === $routingKeys) {
            $this->bindings[$exchange][] = array(
                'routing_key' => $routingKeys,
                'bind_arguments' => $bindArguments,
            );
        }
        if (is_array($routingKeys)) {
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

        if ($declareAndBind) {
            $this->declareAndBind();
        }
    }

    public function declareAndBind()
    {
        $this->declareQueue();

        foreach ($this->bindings as $exchangeName => $configs) {
            foreach ($configs as $config) {
                parent::bind($exchangeName, $config['routing_key'], $config['bind_arguments']);
            }
        }
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getRetryStrategy(): ? RetryStrategyInterface
    {
        return $this->retryStrategy;
    }

    public function getRetryStrategyQueuePattern(): string
    {
        return $this->retryStrategyQueuePattern;
    }
}
