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
 *
 * @final
 *
 * @experimental in 4.2
 */
class Connection
{
    private const ARGUMENTS_AS_INTEGER = [
        'x-delay',
        'x-expires',
        'x-max-length',
        'x-max-length-bytes',
        'x-max-priority',
        'x-message-ttl',
    ];

    private $connectionConfiguration;
    private $exchangeConfiguration;
    private $queueConfiguration;
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

    /**
     * @var \AMQPExchange|null
     */
    private $amqpDelayExchange;

    /**
     * Constructor.
     *
     * Available options:
     *
     *   * host: Hostname of the AMQP service
     *   * port: Port of the AMQP service
     *   * vhost: Virtual Host to use with the AMQP service
     *   * user: Username to use to connect the the AMQP service
     *   * password: Password to use the connect to the AMQP service
     *   * queue:
     *     * name: Name of the queue
     *     * routing_key: The routing key (if any) to use to push the messages to
     *     * flags: Queue flags (Default: AMQP_DURABLE)
     *     * arguments: Extra arguments
     *   * exchange:
     *     * name: Name of the exchange
     *     * type: Type of exchange (Default: fanout)
     *     * flags: Exchange flags (Default: AMQP_DURABLE)
     *     * arguments: Extra arguments
     *   * delay:
     *     * routing_key_pattern: The pattern of the routing key (Default: "delay_%delay%")
     *     * queue_name_pattern: Pattern to use to create the queues (Default: "delay_queue_%delay%")
     *     * exchange_name: Name of the exchange to be used for the retried messages (Default: "retry")
     *   * auto_setup: Enable or not the auto-setup of queues and exchanges (Default: true)
     *   * loop_sleep: Amount of micro-seconds to wait if no message are available (Default: 200000)
     *   * prefetch_count: set channel prefetch count
     */
    public function __construct(array $connectionConfiguration, array $exchangeConfiguration, array $queueConfiguration, AmqpFactory $amqpFactory = null)
    {
        $this->connectionConfiguration = array_replace_recursive([
            'delay' => [
                'routing_key_pattern' => 'delay_%delay%',
                'exchange_name' => 'delay',
                'queue_name_pattern' => 'delay_queue_%delay%',
            ],
        ], $connectionConfiguration);
        $this->exchangeConfiguration = $exchangeConfiguration;
        $this->queueConfiguration = $queueConfiguration;
        $this->amqpFactory = $amqpFactory ?: new AmqpFactory();
    }

    public static function fromDsn(string $dsn, array $options = [], AmqpFactory $amqpFactory = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given AMQP DSN "%s" is invalid.', $dsn));
        }

        $pathParts = isset($parsedUrl['path']) ? explode('/', trim($parsedUrl['path'], '/')) : [];
        $amqpOptions = array_replace_recursive([
            'host' => $parsedUrl['host'] ?? 'localhost',
            'port' => $parsedUrl['port'] ?? 5672,
            'vhost' => isset($pathParts[0]) ? urldecode($pathParts[0]) : '/',
            'queue' => [
                'name' => $queueName = $pathParts[1] ?? 'messages',
            ],
            'exchange' => [
                'name' => $queueName,
            ],
        ], $options);

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

        if (\is_array($queueOptions['arguments'] ?? false)) {
            $queueOptions['arguments'] = self::normalizeQueueArguments($queueOptions['arguments']);
        }

        return new self($amqpOptions, $exchangeOptions, $queueOptions, $amqpFactory);
    }

    private static function normalizeQueueArguments(array $arguments): array
    {
        foreach (self::ARGUMENTS_AS_INTEGER as $key) {
            if (!\array_key_exists($key, $arguments)) {
                continue;
            }

            if (!\is_numeric($arguments[$key])) {
                throw new InvalidArgumentException(sprintf('Integer expected for queue argument "%s", %s given.', $key, \gettype($arguments[$key])));
            }

            $arguments[$key] = (int) $arguments[$key];
        }

        return $arguments;
    }

    /**
     * @param int $delay The delay in milliseconds
     *
     * @throws \AMQPException
     */
    public function publish(string $body, array $headers = [], int $delay = 0): void
    {
        if (0 !== $delay) {
            $this->publishWithDelay($body, $headers, $delay);

            return;
        }

        if ($this->shouldSetup()) {
            $this->setup();
        }

        $flags = $this->queueConfiguration['flags'] ?? AMQP_NOPARAM;
        $attributes = $this->getAttributes($headers);

        $this->exchange()->publish($body, $this->queueConfiguration['routing_key'] ?? null, $flags, $attributes);
    }

    /**
     * @throws \AMQPException
     */
    private function publishWithDelay(string $body, array $headers = [], int $delay)
    {
        if ($this->shouldSetup()) {
            $this->setupDelay($delay);
        }

        $routingKey = $this->getRoutingKeyForDelay($delay);
        $flags = $this->queueConfiguration['flags'] ?? AMQP_NOPARAM;
        $attributes = $this->getAttributes($headers);

        $this->getDelayExchange()->publish($body, $routingKey, $flags, $attributes);
    }

    private function setupDelay(int $delay)
    {
        if (!$this->channel()->isConnected()) {
            $this->clear();
        }

        $exchange = $this->getDelayExchange();
        $exchange->declareExchange();

        $queue = $this->createDelayQueue($delay);
        $queue->declareQueue();
        $queue->bind($exchange->getName(), $this->getRoutingKeyForDelay($delay));
    }

    private function getDelayExchange(): \AMQPExchange
    {
        if (null === $this->amqpDelayExchange) {
            $this->amqpDelayExchange = $this->amqpFactory->createExchange($this->channel());
            $this->amqpDelayExchange->setName($this->connectionConfiguration['delay']['exchange_name']);
            $this->amqpDelayExchange->setType(AMQP_EX_TYPE_DIRECT);
        }

        return $this->amqpDelayExchange;
    }

    /**
     * Creates a delay queue that will delay for a certain amount of time.
     *
     * This works by setting message TTL for the delay and pointing
     * the dead letter exchange to the original exchange. The result
     * is that after the TTL, the message is sent to the dead-letter-exchange,
     * which is the original exchange, resulting on it being put back into
     * the original queue.
     */
    private function createDelayQueue(int $delay)
    {
        $delayConfiguration = $this->connectionConfiguration['delay'];

        $queue = $this->amqpFactory->createQueue($this->channel());
        $queue->setName(str_replace('%delay%', $delay, $delayConfiguration['queue_name_pattern']));
        $queue->setArguments([
            'x-message-ttl' => $delay,
            'x-dead-letter-exchange' => $this->exchange()->getName(),
        ]);

        if (isset($this->queueConfiguration['routing_key'])) {
            // after being released from to DLX, this routing key will be used
            $queue->setArgument('x-dead-letter-routing-key', $this->queueConfiguration['routing_key']);
        }

        return $queue;
    }

    private function getRoutingKeyForDelay(int $delay): string
    {
        return str_replace('%delay%', $delay, $this->connectionConfiguration['delay']['routing_key_pattern']);
    }

    /**
     * Waits and gets a message from the configured queue.
     *
     * @throws \AMQPException
     */
    public function get(): ?\AMQPEnvelope
    {
        if ($this->shouldSetup()) {
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
            $connection = $this->amqpFactory->createConnection($this->connectionConfiguration);
            $connectMethod = 'true' === ($this->connectionConfiguration['persistent'] ?? 'false') ? 'pconnect' : 'connect';

            try {
                $connection->{$connectMethod}();
            } catch (\AMQPConnectionException $e) {
                $credentials = $this->connectionConfiguration;
                $credentials['password'] = '********';

                throw new \AMQPException(sprintf('Could not connect to the AMQP server. Please verify the provided DSN. (%s)', json_encode($credentials)), 0, $e);
            }
            $this->amqpChannel = $this->amqpFactory->createChannel($connection);

            if (isset($this->connectionConfiguration['prefetch_count'])) {
                $this->amqpChannel->setPrefetchCount($this->connectionConfiguration['prefetch_count']);
            }
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

    public function getConnectionConfiguration(): array
    {
        return $this->connectionConfiguration;
    }

    private function clear(): void
    {
        $this->amqpChannel = null;
        $this->amqpQueue = null;
        $this->amqpExchange = null;
    }

    private function shouldSetup(): bool
    {
        if (!\array_key_exists('auto_setup', $this->connectionConfiguration)) {
            return true;
        }

        if (\in_array($this->connectionConfiguration['auto_setup'], [false, 'false'], true)) {
            return false;
        }

        return true;
    }

    private function getAttributes(array $headers): array
    {
        return array_merge_recursive($this->queueConfiguration['attributes'] ?? [], ['headers' => $headers]);
    }
}
