<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Transport;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\LogicException;

/**
 * An AMQP connection.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @final
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

    private const AVAILABLE_OPTIONS = [
        'host',
        'port',
        'vhost',
        'user',
        'login',
        'password',
        'queues',
        'exchange',
        'delay',
        'auto_setup',
        'prefetch_count',
        'retry',
        'persistent',
        'frame_max',
        'channel_max',
        'heartbeat',
        'read_timeout',
        'write_timeout',
        'confirm_timeout',
        'connect_timeout',
        'cacert',
        'cert',
        'key',
        'verify',
        'sasl_method',
    ];

    private const AVAILABLE_QUEUE_OPTIONS = [
        'binding_keys',
        'binding_arguments',
        'flags',
        'arguments',
    ];

    private const AVAILABLE_EXCHANGE_OPTIONS = [
        'name',
        'type',
        'default_publish_routing_key',
        'flags',
        'arguments',
    ];

    private $connectionOptions;
    private $exchangeOptions;
    private $queuesOptions;
    private $amqpFactory;
    private $autoSetupExchange;
    private $autoSetupDelayExchange;

    /**
     * @var \AMQPChannel|null
     */
    private $amqpChannel;

    /**
     * @var \AMQPExchange|null
     */
    private $amqpExchange;

    /**
     * @var \AMQPQueue[]|null
     */
    private $amqpQueues = [];

    /**
     * @var \AMQPExchange|null
     */
    private $amqpDelayExchange;

    public function __construct(array $connectionOptions, array $exchangeOptions, array $queuesOptions, AmqpFactory $amqpFactory = null)
    {
        if (!\extension_loaded('amqp')) {
            throw new LogicException(sprintf('You cannot use the "%s" as the "amqp" extension is not installed.', __CLASS__));
        }

        $this->connectionOptions = array_replace_recursive([
            'delay' => [
                'exchange_name' => 'delays',
                'queue_name_pattern' => 'delay_%exchange_name%_%routing_key%_%delay%',
            ],
        ], $connectionOptions);
        $this->autoSetupExchange = $this->autoSetupDelayExchange = $connectionOptions['auto_setup'] ?? true;
        $this->exchangeOptions = $exchangeOptions;
        $this->queuesOptions = $queuesOptions;
        $this->amqpFactory = $amqpFactory ?? new AmqpFactory();
    }

    /**
     * Creates a connection based on the DSN and options.
     *
     * Available options:
     *
     *   * host: Hostname of the AMQP service
     *   * port: Port of the AMQP service
     *   * vhost: Virtual Host to use with the AMQP service
     *   * user|login: Username to use to connect the AMQP service
     *   * password: Password to use to connect to the AMQP service
     *   * read_timeout: Timeout in for income activity. Note: 0 or greater seconds. May be fractional.
     *   * write_timeout: Timeout in for outcome activity. Note: 0 or greater seconds. May be fractional.
     *   * connect_timeout: Connection timeout. Note: 0 or greater seconds. May be fractional.
     *   * confirm_timeout: Timeout in seconds for confirmation, if none specified transport will not wait for message confirmation. Note: 0 or greater seconds. May be fractional.
     *   * queues[name]: An array of queues, keyed by the name
     *     * binding_keys: The binding keys (if any) to bind to this queue
     *     * binding_arguments: Arguments to be used while binding the queue.
     *     * flags: Queue flags (Default: AMQP_DURABLE)
     *     * arguments: Extra arguments
     *   * exchange:
     *     * name: Name of the exchange
     *     * type: Type of exchange (Default: fanout)
     *     * default_publish_routing_key: Routing key to use when publishing, if none is specified on the message
     *     * flags: Exchange flags (Default: AMQP_DURABLE)
     *     * arguments: Extra arguments
     *   * delay:
     *     * queue_name_pattern: Pattern to use to create the queues (Default: "delay_%exchange_name%_%routing_key%_%delay%")
     *     * exchange_name: Name of the exchange to be used for the delayed/retried messages (Default: "delays")
     *   * auto_setup: Enable or not the auto-setup of queues and exchanges (Default: true)
     *
     *   * Connection tuning options (see http://www.rabbitmq.com/amqp-0-9-1-reference.html#connection.tune for details):
     *     * channel_max: Specifies highest channel number that the server permits. 0 means standard extension limit
     *       (see PHP_AMQP_MAX_CHANNELS constant)
     *     * frame_max: The largest frame size that the server proposes for the connection, including frame header
     *       and end-byte. 0 means standard extension limit (depends on librabbimq default frame size limit)
     *     * heartbeat: The delay, in seconds, of the connection heartbeat that the server wants.
     *       0 means the server does not want a heartbeat. Note, librabbitmq has limited heartbeat support,
     *       which means heartbeats checked only during blocking calls.
     *
     *   TLS support (see https://www.rabbitmq.com/ssl.html for details):
     *     * cacert: Path to the CA cert file in PEM format.
     *     * cert: Path to the client certificate in PEM format.
     *     * key: Path to the client key in PEM format.
     *     * verify: Enable or disable peer verification. If peer verification is enabled then the common name in the
     *       server certificate must match the server name. Peer verification is enabled by default.
     */
    public static function fromDsn(string $dsn, array $options = [], AmqpFactory $amqpFactory = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            // this is a valid URI that parse_url cannot handle when you want to pass all parameters as options
            if (!\in_array($dsn, ['amqp://', 'amqps://'])) {
                throw new InvalidArgumentException(sprintf('The given AMQP DSN "%s" is invalid.', $dsn));
            }

            $parsedUrl = [];
        }

        $useAmqps = 0 === strpos($dsn, 'amqps://');
        $pathParts = isset($parsedUrl['path']) ? explode('/', trim($parsedUrl['path'], '/')) : [];
        $exchangeName = $pathParts[1] ?? 'messages';
        parse_str($parsedUrl['query'] ?? '', $parsedQuery);
        $port = $useAmqps ? 5671 : 5672;

        $amqpOptions = array_replace_recursive([
            'host' => $parsedUrl['host'] ?? 'localhost',
            'port' => $parsedUrl['port'] ?? $port,
            'vhost' => isset($pathParts[0]) ? urldecode($pathParts[0]) : '/',
            'exchange' => [
                'name' => $exchangeName,
            ],
        ], $options, $parsedQuery);

        self::validateOptions($amqpOptions);

        if (isset($parsedUrl['user'])) {
            $amqpOptions['login'] = urldecode($parsedUrl['user']);
        }

        if (isset($parsedUrl['pass'])) {
            $amqpOptions['password'] = urldecode($parsedUrl['pass']);
        }

        if (!isset($amqpOptions['queues'])) {
            $amqpOptions['queues'][$exchangeName] = [];
        }

        $exchangeOptions = $amqpOptions['exchange'];
        $queuesOptions = $amqpOptions['queues'];
        unset($amqpOptions['queues'], $amqpOptions['exchange']);
        if (isset($amqpOptions['auto_setup'])) {
            $amqpOptions['auto_setup'] = filter_var($amqpOptions['auto_setup'], \FILTER_VALIDATE_BOOLEAN);
        }

        $queuesOptions = array_map(function ($queueOptions) {
            if (!\is_array($queueOptions)) {
                $queueOptions = [];
            }
            if (\is_array($queueOptions['arguments'] ?? false)) {
                $queueOptions['arguments'] = self::normalizeQueueArguments($queueOptions['arguments']);
            }

            return $queueOptions;
        }, $queuesOptions);

        if (!$useAmqps) {
            unset($amqpOptions['cacert'], $amqpOptions['cert'], $amqpOptions['key'], $amqpOptions['verify']);
        }

        if ($useAmqps && !self::hasCaCertConfigured($amqpOptions)) {
            throw new InvalidArgumentException('No CA certificate has been provided. Set "amqp.cacert" in your php.ini or pass the "cacert" parameter in the DSN to use SSL. Alternatively, you can use amqp:// to use without SSL.');
        }

        return new self($amqpOptions, $exchangeOptions, $queuesOptions, $amqpFactory);
    }

    private static function validateOptions(array $options): void
    {
        if (0 < \count($invalidOptions = array_diff(array_keys($options), self::AVAILABLE_OPTIONS))) {
            trigger_deprecation('symfony/messenger', '5.1', 'Invalid option(s) "%s" passed to the AMQP Messenger transport. Passing invalid options is deprecated.', implode('", "', $invalidOptions));
        }

        if (isset($options['prefetch_count'])) {
            trigger_deprecation('symfony/messenger', '5.3', 'The "prefetch_count" option passed to the AMQP Messenger transport has no effect and should not be used.');
        }

        if (\is_array($options['queues'] ?? false)) {
            foreach ($options['queues'] as $queue) {
                if (!\is_array($queue)) {
                    continue;
                }

                if (0 < \count($invalidQueueOptions = array_diff(array_keys($queue), self::AVAILABLE_QUEUE_OPTIONS))) {
                    trigger_deprecation('symfony/messenger', '5.1', 'Invalid queue option(s) "%s" passed to the AMQP Messenger transport. Passing invalid queue options is deprecated.', implode('", "', $invalidQueueOptions));
                }
            }
        }

        if (\is_array($options['exchange'] ?? false)
            && 0 < \count($invalidExchangeOptions = array_diff(array_keys($options['exchange']), self::AVAILABLE_EXCHANGE_OPTIONS))) {
            trigger_deprecation('symfony/messenger', '5.1', 'Invalid exchange option(s) "%s" passed to the AMQP Messenger transport. Passing invalid exchange options is deprecated.', implode('", "', $invalidExchangeOptions));
        }
    }

    private static function normalizeQueueArguments(array $arguments): array
    {
        foreach (self::ARGUMENTS_AS_INTEGER as $key) {
            if (!\array_key_exists($key, $arguments)) {
                continue;
            }

            if (!is_numeric($arguments[$key])) {
                throw new InvalidArgumentException(sprintf('Integer expected for queue argument "%s", "%s" given.', $key, get_debug_type($arguments[$key])));
            }

            $arguments[$key] = (int) $arguments[$key];
        }

        return $arguments;
    }

    private static function hasCaCertConfigured(array $amqpOptions): bool
    {
        return (isset($amqpOptions['cacert']) && '' !== $amqpOptions['cacert']) || '' !== \ini_get('amqp.cacert');
    }

    /**
     * @throws \AMQPException
     */
    public function publish(string $body, array $headers = [], int $delayInMs = 0, AmqpStamp $amqpStamp = null): void
    {
        $this->clearWhenDisconnected();

        if ($this->autoSetupExchange) {
            $this->setupExchangeAndQueues(); // also setup normal exchange for delayed messages so delay queue can DLX messages to it
        }

        if (0 !== $delayInMs) {
            $this->publishWithDelay($body, $headers, $delayInMs, $amqpStamp);

            return;
        }

        $this->publishOnExchange(
            $this->exchange(),
            $body,
            $this->getRoutingKeyForMessage($amqpStamp),
            $headers,
            $amqpStamp
        );
    }

    /**
     * Returns an approximate count of the messages in defined queues.
     */
    public function countMessagesInQueues(): int
    {
        return array_sum(array_map(function ($queueName) {
            return $this->queue($queueName)->declareQueue();
        }, $this->getQueueNames()));
    }

    /**
     * @throws \AMQPException
     */
    private function publishWithDelay(string $body, array $headers, int $delay, AmqpStamp $amqpStamp = null)
    {
        $routingKey = $this->getRoutingKeyForMessage($amqpStamp);
        $isRetryAttempt = $amqpStamp ? $amqpStamp->isRetryAttempt() : false;

        $this->setupDelay($delay, $routingKey, $isRetryAttempt);

        $this->publishOnExchange(
            $this->getDelayExchange(),
            $body,
            $this->getRoutingKeyForDelay($delay, $routingKey, $isRetryAttempt),
            $headers,
            $amqpStamp
        );
    }

    private function publishOnExchange(\AMQPExchange $exchange, string $body, string $routingKey = null, array $headers = [], AmqpStamp $amqpStamp = null)
    {
        $attributes = $amqpStamp ? $amqpStamp->getAttributes() : [];
        $attributes['headers'] = array_merge($attributes['headers'] ?? [], $headers);
        $attributes['delivery_mode'] = $attributes['delivery_mode'] ?? 2;
        $attributes['timestamp'] = $attributes['timestamp'] ?? time();

        $exchange->publish(
            $body,
            $routingKey,
            $amqpStamp ? $amqpStamp->getFlags() : \AMQP_NOPARAM,
            $attributes
        );

        if ('' !== ($this->connectionOptions['confirm_timeout'] ?? '')) {
            $this->channel()->waitForConfirm((float) $this->connectionOptions['confirm_timeout']);
        }
    }

    private function setupDelay(int $delay, ?string $routingKey, bool $isRetryAttempt)
    {
        if ($this->autoSetupDelayExchange) {
            $this->setupDelayExchange();
        }

        $queue = $this->createDelayQueue($delay, $routingKey, $isRetryAttempt);
        $queue->declareQueue(); // the delay queue always need to be declared because the name is dynamic and cannot be declared in advance
        $queue->bind($this->connectionOptions['delay']['exchange_name'], $this->getRoutingKeyForDelay($delay, $routingKey, $isRetryAttempt));
    }

    private function getDelayExchange(): \AMQPExchange
    {
        if (null === $this->amqpDelayExchange) {
            $this->amqpDelayExchange = $this->amqpFactory->createExchange($this->channel());
            $this->amqpDelayExchange->setName($this->connectionOptions['delay']['exchange_name']);
            $this->amqpDelayExchange->setType(\AMQP_EX_TYPE_DIRECT);
            $this->amqpDelayExchange->setFlags(\AMQP_DURABLE);
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
    private function createDelayQueue(int $delay, ?string $routingKey, bool $isRetryAttempt): \AMQPQueue
    {
        $queue = $this->amqpFactory->createQueue($this->channel());
        $queue->setName($this->getRoutingKeyForDelay($delay, $routingKey, $isRetryAttempt));
        $queue->setFlags(\AMQP_DURABLE);
        $queue->setArguments([
            'x-message-ttl' => $delay,
            // delete the delay queue 10 seconds after the message expires
            // publishing another message redeclares the queue which renews the lease
            'x-expires' => $delay + 10000,
            // message should be broadcasted to all consumers during delay, but to only one queue during retry
            // empty name is default direct exchange
            'x-dead-letter-exchange' => $isRetryAttempt ? '' : $this->exchangeOptions['name'],
            // after being released from to DLX, make sure the original routing key will be used
            // we must use an empty string instead of null for the argument to be picked up
            'x-dead-letter-routing-key' => $routingKey ?? '',
        ]);

        return $queue;
    }

    private function getRoutingKeyForDelay(int $delay, ?string $finalRoutingKey, bool $isRetryAttempt): string
    {
        $action = $isRetryAttempt ? '_retry' : '_delay';

        return str_replace(
            ['%delay%', '%exchange_name%', '%routing_key%'],
            [$delay, $this->exchangeOptions['name'], $finalRoutingKey ?? ''],
            $this->connectionOptions['delay']['queue_name_pattern']
        ).$action;
    }

    /**
     * Gets a message from the specified queue.
     *
     * @throws \AMQPException
     */
    public function get(string $queueName): ?\AMQPEnvelope
    {
        $this->clearWhenDisconnected();

        if ($this->autoSetupExchange) {
            $this->setupExchangeAndQueues();
        }

        if (false !== $message = $this->queue($queueName)->get()) {
            return $message;
        }

        return null;
    }

    public function ack(\AMQPEnvelope $message, string $queueName): bool
    {
        return $this->queue($queueName)->ack($message->getDeliveryTag());
    }

    public function nack(\AMQPEnvelope $message, string $queueName, int $flags = \AMQP_NOPARAM): bool
    {
        return $this->queue($queueName)->nack($message->getDeliveryTag(), $flags);
    }

    public function setup(): void
    {
        $this->setupExchangeAndQueues();
        $this->setupDelayExchange();
    }

    private function setupExchangeAndQueues(): void
    {
        $this->exchange()->declareExchange();

        foreach ($this->queuesOptions as $queueName => $queueConfig) {
            $this->queue($queueName)->declareQueue();
            foreach ($queueConfig['binding_keys'] ?? [null] as $bindingKey) {
                $this->queue($queueName)->bind($this->exchangeOptions['name'], $bindingKey, $queueConfig['binding_arguments'] ?? []);
            }
        }
        $this->autoSetupExchange = false;
    }

    private function setupDelayExchange(): void
    {
        $this->getDelayExchange()->declareExchange();
        $this->autoSetupDelayExchange = false;
    }

    /**
     * @return string[]
     */
    public function getQueueNames(): array
    {
        return array_keys($this->queuesOptions);
    }

    public function channel(): \AMQPChannel
    {
        if (null === $this->amqpChannel) {
            $connection = $this->amqpFactory->createConnection($this->connectionOptions);
            $connectMethod = 'true' === ($this->connectionOptions['persistent'] ?? 'false') ? 'pconnect' : 'connect';

            try {
                $connection->{$connectMethod}();
            } catch (\AMQPConnectionException $e) {
                throw new \AMQPException('Could not connect to the AMQP server. Please verify the provided DSN.', 0, $e);
            }
            $this->amqpChannel = $this->amqpFactory->createChannel($connection);

            if ('' !== ($this->connectionOptions['confirm_timeout'] ?? '')) {
                $this->amqpChannel->confirmSelect();
                $this->amqpChannel->setConfirmCallback(
                    static function (): bool {
                        return false;
                    },
                    static function (): bool {
                        return false;
                    }
                );
            }
        }

        return $this->amqpChannel;
    }

    public function queue(string $queueName): \AMQPQueue
    {
        if (!isset($this->amqpQueues[$queueName])) {
            $queueConfig = $this->queuesOptions[$queueName];

            $amqpQueue = $this->amqpFactory->createQueue($this->channel());
            $amqpQueue->setName($queueName);
            $amqpQueue->setFlags($queueConfig['flags'] ?? \AMQP_DURABLE);

            if (isset($queueConfig['arguments'])) {
                $amqpQueue->setArguments($queueConfig['arguments']);
            }

            $this->amqpQueues[$queueName] = $amqpQueue;
        }

        return $this->amqpQueues[$queueName];
    }

    public function exchange(): \AMQPExchange
    {
        if (null === $this->amqpExchange) {
            $this->amqpExchange = $this->amqpFactory->createExchange($this->channel());
            $this->amqpExchange->setName($this->exchangeOptions['name']);
            $this->amqpExchange->setType($this->exchangeOptions['type'] ?? \AMQP_EX_TYPE_FANOUT);
            $this->amqpExchange->setFlags($this->exchangeOptions['flags'] ?? \AMQP_DURABLE);

            if (isset($this->exchangeOptions['arguments'])) {
                $this->amqpExchange->setArguments($this->exchangeOptions['arguments']);
            }
        }

        return $this->amqpExchange;
    }

    private function clearWhenDisconnected(): void
    {
        if (!$this->channel()->isConnected()) {
            $this->amqpChannel = null;
            $this->amqpQueues = [];
            $this->amqpExchange = null;
            $this->amqpDelayExchange = null;
        }
    }

    private function getDefaultPublishRoutingKey(): ?string
    {
        return $this->exchangeOptions['default_publish_routing_key'] ?? null;
    }

    public function purgeQueues()
    {
        foreach ($this->getQueueNames() as $queueName) {
            $this->queue($queueName)->purge();
        }
    }

    private function getRoutingKeyForMessage(?AmqpStamp $amqpStamp): ?string
    {
        return (null !== $amqpStamp ? $amqpStamp->getRoutingKey() : null) ?? $this->getDefaultPublishRoutingKey();
    }
}

if (!class_exists(\Symfony\Component\Messenger\Transport\AmqpExt\Connection::class, false)) {
    class_alias(Connection::class, \Symfony\Component\Messenger\Transport\AmqpExt\Connection::class);
}
