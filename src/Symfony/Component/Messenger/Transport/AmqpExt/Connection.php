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

/**
 * An AMQP connection.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class Connection
{
    private const DEFAULT_MESSAGE_TTL_IN_MILLI_SECONDS = 10000;
    private const DEFAULT_MAX_ATTEMPTS = 3;

    private $connectionConfiguration;
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

    /**
     * @var \AMQPExchange|null
     */
    private $amqpRetryExchange;

    /**
     * Available options:
     *
     *   * host: Hostname of the AMQP service
     *   * port: Port of the AMQP service
     *   * vhost: Virtual Host to use with the AMQP service
     *   * user: Username to use to connect the the AMQP service
     *   * password: Username to use the connect to the AMQP service
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
     *   * retry:
     *     * attempts: Number of times it will try to retry
     *     * routing_key_pattern: The pattern of the routing key (Default: "attempt_%attempt%")
     *     * dead_queue: Name of the queue in which messages that retry more than attempts time are pushed to
     *     * dead_routing_key: Routing key name for the dead queue (Default: "dead")
     *     * queue_name_pattern: Pattern to use to create the queues (Default: "retry_queue_%attempt%")
     *     * exchange_name: Name of the exchange to be used for the retried messages (Default: "retry")
     *     * ttl: Key-value pairs of attempt number -> seconds to wait. If not configured, 10 seconds will be waited each attempt.
     *   * auto-setup: Enable or not the auto-setup of queues and exchanges (Default: true)
     *   * loop_sleep: Amount of micro-seconds to wait if no message are available (Default: 200000)
     */
    public function __construct(array $connectionConfiguration, array $exchangeConfiguration, array $queueConfiguration, bool $debug = false, AmqpFactory $amqpFactory = null)
    {
        $this->connectionConfiguration = array_replace_recursive(array(
            'retry' => array(
                'routing_key_pattern' => 'attempt_%attempt%',
                'dead_routing_key' => 'dead',
                'exchange_name' => 'retry',
                'queue_name_pattern' => 'retry_queue_%attempt%',
            ),
        ), $connectionConfiguration);
        $this->debug = $debug;
        $this->exchangeConfiguration = $exchangeConfiguration;
        $this->queueConfiguration = $queueConfiguration;
        $this->amqpFactory = $amqpFactory ?: new AmqpFactory();
    }

    public static function fromDsn(string $dsn, array $options = array(), bool $debug = false, AmqpFactory $amqpFactory = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new \InvalidArgumentException(sprintf('The given AMQP DSN "%s" is invalid.', $dsn));
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
     * @throws \AMQPException
     */
    public function publishForRetry(\AMQPEnvelope $message): bool
    {
        if (!isset($this->connectionConfiguration['retry'])) {
            return false;
        }

        $retryConfiguration = $this->connectionConfiguration['retry'];
        $attemptNumber = ((int) $message->getHeader('symfony-messenger-attempts') ?: 0) + 1;

        if ($this->shouldSetup()) {
            $this->setupRetry($retryConfiguration, $attemptNumber);
        }

        $maximumAttempts = $retryConfiguration['attempts'] ?? self::DEFAULT_MAX_ATTEMPTS;
        $routingKey = str_replace('%attempt%', $attemptNumber, $retryConfiguration['routing_key_pattern']);

        if ($attemptNumber > $maximumAttempts) {
            if (!isset($retryConfiguration['dead_queue'])) {
                return false;
            }

            $routingKey = $retryConfiguration['dead_routing_key'];
        }

        $retriedMessageAttributes = array(
            'headers' => array_merge($message->getHeaders(), array('symfony-messenger-attempts' => (string) $attemptNumber)),
        );

        if ($deliveryMode = $message->getDeliveryMode()) {
            $retriedMessageAttributes['delivery_mode'] = $deliveryMode;
        }
        if ($userId = $message->getUserId()) {
            $retriedMessageAttributes['user_id'] = $userId;
        }
        if (null !== $priority = $message->getPriority()) {
            $retriedMessageAttributes['priority'] = $priority;
        }
        if ($replyTo = $message->getReplyTo()) {
            $retriedMessageAttributes['reply_to'] = $replyTo;
        }

        $this->retryExchange($retryConfiguration)->publish(
            $message->getBody(),
            $routingKey,
            AMQP_NOPARAM,
            $retriedMessageAttributes
        );

        return true;
    }

    private function setupRetry(array $retryConfiguration, int $attemptNumber)
    {
        if (!$this->channel()->isConnected()) {
            $this->clear();
        }

        $exchange = $this->retryExchange($retryConfiguration);
        $exchange->declareExchange();

        $queue = $this->retryQueue($retryConfiguration, $attemptNumber);
        $queue->declareQueue();
        $queue->bind($exchange->getName(), str_replace('%attempt%', $attemptNumber, $retryConfiguration['routing_key_pattern']));

        if (isset($retryConfiguration['dead_queue'])) {
            $queue = $this->amqpFactory->createQueue($this->channel());
            $queue->setName($retryConfiguration['dead_queue']);
            $queue->declareQueue();
            $queue->bind($exchange->getName(), $retryConfiguration['dead_routing_key']);
        }
    }

    private function retryExchange(array $retryConfiguration): \AMQPExchange
    {
        if (null === $this->amqpRetryExchange) {
            $this->amqpRetryExchange = $this->amqpFactory->createExchange($this->channel());
            $this->amqpRetryExchange->setName($retryConfiguration['exchange_name']);
            $this->amqpRetryExchange->setType(AMQP_EX_TYPE_DIRECT);
        }

        return $this->amqpRetryExchange;
    }

    private function retryQueue(array $retryConfiguration, int $attemptNumber)
    {
        $queue = $this->amqpFactory->createQueue($this->channel());
        $queue->setName(str_replace('%attempt%', $attemptNumber, $retryConfiguration['queue_name_pattern']));
        $queue->setArguments(array(
            'x-message-ttl' => $retryConfiguration['ttl'][$attemptNumber - 1] ?? self::DEFAULT_MESSAGE_TTL_IN_MILLI_SECONDS,
            'x-dead-letter-exchange' => $this->exchange()->getName(),
        ));

        if (isset($this->queueConfiguration['routing_key'])) {
            $queue->setArgument('x-dead-letter-routing-key', $this->queueConfiguration['routing_key']);
        }

        return $queue;
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
            $connection = $this->amqpFactory->createConnection($this->connectionConfiguration);
            $connectMethod = 'true' === ($this->connectionConfiguration['persistent'] ?? 'false') ? 'pconnect' : 'connect';

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
        return !array_key_exists('auto-setup', $this->connectionConfiguration) || !\in_array($this->connectionConfiguration['auto-setup'], array(false, 'false'), true);
    }
}
