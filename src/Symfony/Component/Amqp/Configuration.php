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

class Configuration
{
    private const DEFAULT_QUEUE_CONFIGURATION = array(
        'arguments' => array(),
        'retry_strategy' => null,
        'retry_strategy_options' => array(),
        'thresholds' => array('warning' => null, 'critical' => null),
    );

    private const DEFAULT_EXCHANGE_CONFIGURATION = array(
        'arguments' => array(),
    );

    private $queuesConfiguration = array();
    private $exchangesConfiguration = array();

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
    public function __construct(array $queuesConfiguration = array(), array $exchangesConfiguration = array())
    {
        $this->setQueuesConfiguration($queuesConfiguration);
        $this->setExchangesConfiguration($exchangesConfiguration);
    }

    public function getQueuesConfiguration(): array
    {
        return $this->queuesConfiguration;
    }

    public function getQueueConfiguration(string $queueName): ?array
    {
        return $this->queuesConfiguration[$queueName] ?? null;
    }

    public function getExchangesConfiguration(): array
    {
        return $this->exchangesConfiguration;
    }

    public function getExchangeConfiguration(string $exchangeName): ?array
    {
        return $this->exchangesConfiguration[$exchangeName] ?? null;
    }

    private function setQueuesConfiguration(array $queuesConfiguration)
    {
        foreach ($queuesConfiguration as $configuration) {
            if (!isset($configuration['name'])) {
                throw new InvalidArgumentException('The key "name" is required to configure a Queue.');
            }

            if (isset($this->queuesConfiguration[$configuration['name']])) {
                throw new InvalidArgumentException(sprintf('A queue named "%s" already exists.', $configuration['name']));
            }

            $configuration = array_replace_recursive(self::DEFAULT_QUEUE_CONFIGURATION, $configuration);

            $this->queuesConfiguration[$configuration['name']] = $configuration;
        }
    }

    private function setExchangesConfiguration(array $exchangesConfiguration)
    {
        foreach ($exchangesConfiguration as $configuration) {
            if (!isset($configuration['name'])) {
                throw new InvalidArgumentException('The key "name" is required to configure an Exchange.');
            }

            if (isset($this->exchangesConfiguration[$configuration['name']])) {
                throw new InvalidArgumentException(sprintf('An exchange named "%s" already exists.', $configuration['name']));
            }

            $configuration = array_replace_recursive(self::DEFAULT_QUEUE_CONFIGURATION, $configuration);

            $this->exchangesConfiguration[$configuration['name']] = $configuration;
        }
    }
}
