<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * An AMQP connection.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class Connection
{
    private $connectionCredentials;
    private $exchangeConfiguration;
    private $queueConfiguration;
    private $debug;
    private $amqpFactory;

    /**
     * @var \AMQPChannel|null
     */
    private $amqpChannel;

    /**
     * @var \AMQPExchange|null
     */
    private $amqpExchange;

    /**
     * @var \AMQPQueue|null
     */
    private $amqpQueue;

    public function __construct(array $connectionCredentials, array $exchangeConfiguration, array $queueConfiguration, bool $debug = false, AmqpFactory $amqpFactory = null)
    {
        $this->connectionCredentials = $connectionCredentials;
        $this->debug = $debug;
        $this->exchangeConfiguration = $exchangeConfiguration;
        $this->queueConfiguration = $queueConfiguration;
        $this->amqpFactory = $amqpFactory ?: new AmqpFactory();
    }

    public static function fromDsn(string $dsn, array $options = array(), bool $debug = false, AmqpFactory $amqpFactory = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given AMQP DSN "%s" is invalid.', $dsn));
        }

        $pathParts = isset($parsedUrl['path']) ? explode('/', trim($parsedUrl['path'], '/')) : array();
        $amqpOptions = array_replace_recursive(array(
            'host' => $parsedUrl['host'] ?? 'localhost',
            'port' => $parsedUrl['port'] ?? 5672,
            'vhost' => isset($pathParts[0]) ? urldecode($pathParts[0]) : '/',
            'queue' => array(
                'name' => $queueName = $pathParts[1] ?? 'messages',
            ),
            'exchange' => array(
                'name' => $queueName,
            ),
        ), $options);

        if (isset($parsedUrl['user'])) {
            $amqpOptions['login'] = $parsedUrl['user'];
        }

        if (isset($parsedUrl['pass'])) {
            $amqpOptions['password'] = $parsedUrl['pass'];
        }

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $parsedQuery);

            $amqpOptions = array_replace_recursive($amqpOptions, $parsedQuery);
        }

        $exchangeOptions = $amqpOptions['exchange'];
        $queueOptions = $amqpOptions['queue'];

        unset($amqpOptions['queue'], $amqpOptions['exchange']);

        return new self($amqpOptions, $exchangeOptions, $queueOptions, $debug, $amqpFactory);
    }

    /**
     * @throws \AMQPException
     */
    public function publish(string $body, array $headers = array()): void
    {
        if ($this->debug && $this->shouldSetup()) {
            $this->setup();
        }

        $this->exchange()->publish($body, null, AMQP_NOPARAM, array('headers' => $headers));
    }

    /**
     * Waits and gets a message from the configured queue.
     *
     * @throws \AMQPException
     */
    public function get(): ?\AMQPEnvelope
    {
        if ($this->debug && $this->shouldSetup()) {
            $this->setup();
        }

        try {
            if (false !== $message = $this->queue()->get()) {
                return $message;
            }
        } catch (\AMQPQueueException $e) {
            if (404 === $e->getCode() && $this->shouldSetup()) {
                // If we get a 404 for the queue, it means we need to setup the exchange & queue.
                $this->setup();

                return $this->get();
            }

            throw $e;
        }

        return null;
    }

    public function ack(\AMQPEnvelope $message): bool
    {
        return $this->queue()->ack($message->getDeliveryTag());
    }

    public function reject(\AMQPEnvelope $message): bool
    {
        return $this->queue()->reject($message->getDeliveryTag());
    }

    public function nack(\AMQPEnvelope $message, int $flags = AMQP_NOPARAM): bool
    {
        return $this->queue()->nack($message->getDeliveryTag(), $flags);
    }

    public function setup(): void
    {
        if (!$this->channel()->isConnected()) {
            $this->clear();
        }

        $this->exchange()->declareExchange();

        $this->queue()->declareQueue();
        $this->queue()->bind($this->exchange()->getName(), $this->queueConfiguration['routing_key'] ?? null);
    }

    public function channel(): \AMQPChannel
    {
        if (null === $this->amqpChannel) {
            $connection = $this->amqpFactory->createConnection($this->connectionCredentials);
            $connectMethod = 'true' === ($this->connectionCredentials['persistent'] ?? 'false') ? 'pconnect' : 'connect';

            if (false === $connection->{$connectMethod}()) {
                throw new \AMQPException('Could not connect to the AMQP server. Please verify the provided DSN.');
            }

            $this->amqpChannel = $this->amqpFactory->createChannel($connection);
        }

        return $this->amqpChannel;
    }

    public function queue(): \AMQPQueue
    {
        if (null === $this->amqpQueue) {
            $this->amqpQueue = $this->amqpFactory->createQueue($this->channel());
            $this->amqpQueue->setName($this->queueConfiguration['name']);
            $this->amqpQueue->setFlags($this->queueConfiguration['flags'] ?? AMQP_DURABLE);

            if (isset($this->queueConfiguration['arguments'])) {
                $this->amqpQueue->setArguments($this->queueConfiguration['arguments']);
            }
        }

        return $this->amqpQueue;
    }

    public function exchange(): \AMQPExchange
    {
        if (null === $this->amqpExchange) {
            $this->amqpExchange = $this->amqpFactory->createExchange($this->channel());
            $this->amqpExchange->setName($this->exchangeConfiguration['name']);
            $this->amqpExchange->setType($this->exchangeConfiguration['type'] ?? AMQP_EX_TYPE_FANOUT);
            $this->amqpExchange->setFlags($this->exchangeConfiguration['flags'] ?? AMQP_DURABLE);

            if (isset($this->exchangeConfiguration['arguments'])) {
                $this->amqpExchange->setArguments($this->exchangeConfiguration['arguments']);
            }
        }

        return $this->amqpExchange;
    }

    public function getConnectionCredentials(): array
    {
        return $this->connectionCredentials;
    }

    private function clear(): void
    {
        $this->amqpChannel = null;
        $this->amqpQueue = null;
        $this->amqpExchange = null;
    }

    private function shouldSetup(): bool
    {
        return !array_key_exists('auto-setup', $this->connectionCredentials) || !\in_array($this->connectionCredentials['auto-setup'], array(false, 'false'), true);
    }
}
