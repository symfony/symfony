<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Transport;

use Psr\Log\LoggerInterface;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\TransportException;

class Connection
{
    private const DSN_PROTOCOL_KAFKA = 'kafka://';

    private const AVAILABLE_OPTIONS = [
        'consumer',
        'producer',
        'transport_name',
    ];

    private const DEFAULT_CONSUMER_OPTIONS = [
        'commit_async' => false,
        'consume_timeout_ms' => 10000,
        'topics' => [],
        'conf_options' => [],
    ];

    private const REQUIRED_CONSUMER_CONF_OPTIONS = [
        'group.id',
        'metadata.broker.list',
    ];

    private const DEFAULT_PRODUCER_OPTIONS = [
        'poll_timeout_ms' => 0,
        'flush_timeout_ms' => 10000,
        'topic' => null,
        'conf_options' => [],
    ];

    private const REQUIRED_PRODUCER_CONF_OPTIONS = [
        'metadata.broker.list',
    ];

    private bool $consumerIsSubscribed = false;
    private ?KafkaConsumer $consumer = null;
    private ?Producer $producer = null;

    private KafkaFactory $kafkaFactory;

    /**
     * @psalm-param array<string, bool|float|int|string|array<string>> $consumerConfig
     * @psalm-param array<string, bool|float|int|string|array<string>> $producerConfig
     */
    private function __construct(
        private readonly array $consumerConfig,
        private readonly array $producerConfig,
        private readonly LoggerInterface $logger,
        KafkaFactory $kafkaFactory = null,
    ) {
        if (!\extension_loaded('rdkafka')) {
            throw new LogicException(sprintf('You cannot use the "%s" as the "rdkafka" extension is not installed.', __CLASS__));
        }

        $this->kafkaFactory = $kafkaFactory ?? new KafkaFactory($logger);
    }

    /** @psalm-param array<string, bool|float|int|string|array<string>> $options */
    public static function fromDsn(string $dsn, array $options, LoggerInterface $logger, KafkaFactory $kafkaFactory): self
    {
        $options = self::setupOptions($dsn, $options);

        return new self($options['consumer'], $options['producer'], $logger, $kafkaFactory);
    }

    /** @psalm-param array<string, bool|float|int|string|array<string>> $options */
    private static function setupOptions(string $dsn, array $options): array
    {
        $invalidOptions = array_diff(
            array_keys($options),
            self::AVAILABLE_OPTIONS,
        );

        if (0 < \count($invalidOptions)) {
            throw new \InvalidArgumentException(sprintf('Invalid option(s) "%s" passed to the Kafka Messenger transport.', implode('", "', $invalidOptions)));
        }

        if (
            !\array_key_exists('consumer', $options)
            && !\array_key_exists('producer', $options)
        ) {
            throw new LogicException('At least one of "consumer" or "producer" options is required for the Kafka Messenger transport.');
        }

        $brokerList = implode(',', self::stripProtocol($dsn));

        return [
            'consumer' => self::setupConsumerOptions($brokerList, $options['consumer'] ?? []),
            'producer' => self::setupProducerOptions($brokerList, $options['producer'] ?? []),
        ];
    }

    /** @psalm-param array<string, bool|float|int|string|array<string>> $options */
    private static function setupConsumerOptions(string $brokerList, array $configOptions): array
    {
        if (0 === \count($configOptions)) {
            return self::DEFAULT_CONSUMER_OPTIONS;
        }

        $invalidOptions = array_diff(
            array_keys($configOptions),
            array_keys(self::DEFAULT_CONSUMER_OPTIONS),
        );

        if (0 < \count($invalidOptions)) {
            throw new \InvalidArgumentException(sprintf('Invalid option(s) "%s" passed to the Kafka Messenger transport consumer.', implode('", "', $invalidOptions)));
        }

        $options = array_merge(
            self::DEFAULT_CONSUMER_OPTIONS,
            $configOptions,
        );

        if (!\is_bool($options['commit_async'])) {
            throw new LogicException(sprintf('The "commit_async" option type must be boolean, %s given in the Kafka Messenger transport consumer.', \gettype($options['commit_async'])));
        }

        if (!\is_int($options['consume_timeout_ms'])) {
            throw new LogicException(sprintf('The "consume_timeout_ms" option type must be integer, %s given in the Kafka Messenger transport consumer.', \gettype($options['consume_timeout_ms'])));
        }

        if (!\is_array($options['topics'])) {
            throw new LogicException(sprintf('The "topics" option type must be array, %s given in the Kafka Messenger transport consumer.', \gettype($options['topics'])));
        }

        $options['conf_options']['metadata.broker.list'] = $brokerList;
        self::validateKafkaOptions($options['conf_options'], KafkaOption::consumer());

        if (self::REQUIRED_CONSUMER_CONF_OPTIONS !== array_intersect(self::REQUIRED_CONSUMER_CONF_OPTIONS, array_keys($options['conf_options']))) {
            throw new LogicException(sprintf('The conf_option(s) "%s" are required for the Kafka Messenger transport consumer.', implode('", "', self::REQUIRED_CONSUMER_CONF_OPTIONS)));
        }

        return $options;
    }

    /** @psalm-param array<string, bool|float|int|string|array<string>> $options */
    private static function setupProducerOptions(string $brokerList, array $configOptions): array
    {
        if (0 === \count($configOptions)) {
            return self::DEFAULT_PRODUCER_OPTIONS;
        }

        $invalidOptions = array_diff(
            array_keys($configOptions),
            array_keys(self::DEFAULT_PRODUCER_OPTIONS),
        );

        if (0 < \count($invalidOptions)) {
            throw new \InvalidArgumentException(sprintf('Invalid option(s) "%s" passed to the Kafka Messenger transport producer.', implode('", "', $invalidOptions)));
        }

        $options = array_merge(
            self::DEFAULT_PRODUCER_OPTIONS,
            $configOptions,
        );

        if (!\is_int($options['poll_timeout_ms'])) {
            throw new LogicException(sprintf('The "poll_timeout_ms" option type must be integer, "%s" given in the Kafka Messenger transport producer.', \gettype($options['poll_timeout_ms'])));
        }

        if (!\is_int($options['flush_timeout_ms'])) {
            throw new LogicException(sprintf('The "flush_timeout_ms" option type must be integer, "%s" given in the Kafka Messenger transport producer.', \gettype($options['flush_timeout_ms'])));
        }

        if (!\is_string($options['topic']) && null !== $options['topic']) {
            throw new LogicException(sprintf('The "topic" option type must be string, "%s" given in the Kafka Messenger transport producer.', \gettype($options['topic'])));
        }

        $options['conf_options']['metadata.broker.list'] = $brokerList;
        self::validateKafkaOptions($options['conf_options'], KafkaOption::producer());

        if (self::REQUIRED_PRODUCER_CONF_OPTIONS !== array_intersect_key(self::REQUIRED_PRODUCER_CONF_OPTIONS, array_keys($options['conf_options']))) {
            throw new LogicException(sprintf('The conf_option(s) "%s" are required for the Kafka Messenger transport producer.', implode('", "', self::REQUIRED_PRODUCER_CONF_OPTIONS)));
        }

        return $options;
    }

    private static function validateKafkaOptions(array $values, array $availableKafkaOptions): void
    {
        foreach ($values as $key => $value) {
            if (!isset($availableKafkaOptions[$key])) {
                throw new \InvalidArgumentException(sprintf('Invalid conf_options option "%s" passed to the Kafka Messenger transport.', $key));
            }

            if (!\is_string($value)) {
                throw new LogicException(sprintf('Kafka config value "%s" must be a string, got "%s".', $key, get_debug_type($value)));
            }
        }
    }

    private static function stripProtocol(string $dsn): array
    {
        $brokers = [];
        foreach (explode(',', $dsn) as $currentBroker) {
            $brokers[] = str_replace(self::DSN_PROTOCOL_KAFKA, '', $currentBroker);
        }

        return $brokers;
    }

    public function get(): Message
    {
        $consumer = $this->getConsumer();

        if (!$this->consumerIsSubscribed) {
            $consumer->subscribe($this->consumerConfig['topics']);
            $this->consumerIsSubscribed = true;
        }

        try {
            $message = $consumer->consume($this->consumerConfig['consume_timeout_ms']);

            match ($message->err) {
                \RD_KAFKA_RESP_ERR_NO_ERROR => $this->logger->debug(sprintf(
                    'Message consumed from Kafka on partition %s: %s',
                    $message->partition,
                    $message->payload,
                )),
                \RD_KAFKA_RESP_ERR__PARTITION_EOF => $this->logger->info(
                    'No more messages; Waiting for more'
                ),
                \RD_KAFKA_RESP_ERR__TIMED_OUT => $this->logger->debug(
                    'Timed out waiting for message'
                ),
                \RD_KAFKA_RESP_ERR__TRANSPORT => $this->logger->warning(
                    'Kafka: Broker transport failure.',
                ),
                default => $this->logger->error(sprintf(
                    'Error occurred while consuming message from Kafka: %s',
                    $message->errstr(),
                )),
            };

            return $message;
        } catch (\RdKafka\Exception $e) {
            $this->logger->error(sprintf(
                'Error occurred while consuming message from Kafka: %s',
                $e->getMessage(),
            ));

            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function ack(Message $message): void
    {
        $consumer = $this->getConsumer();

        if ($this->consumerConfig['commit_async']) {
            $consumer->commitAsync($message);

            $this->logger->info(sprintf(
                'Offset topic=%s partition=%s offset=%s to be committed asynchronously.',
                $message->topic_name,
                $message->partition,
                $message->offset,
            ));
        } else {
            $consumer->commit($message);

            $this->logger->info(sprintf(
                'Offset topic=%s partition=%s offset=%s successfully committed.',
                $message->topic_name,
                $message->partition,
                $message->offset,
            ));
        }
    }

    /** @psalm-param array<string, string> $headers */
    public function publish(int $partition, int $messageFlags, string $body, string $key = null, array $headers = []): void
    {
        if (!$this->producerConfig['topic']) {
            throw new LogicException('No topic configured for the producer.');
        }

        $producer = $this->getProducer();

        $topic = $producer->newTopic($this->producerConfig['topic']);
        $topic->producev(
            $partition,
            $messageFlags,
            $body,
            $key,
            $headers,
        );

        $producer->poll($this->producerConfig['poll_timeout_ms']);
        $producer->flush($this->producerConfig['flush_timeout_ms']);
    }

    private function getConsumer(): KafkaConsumer
    {
        return $this->consumer ??= $this->kafkaFactory->createConsumer($this->consumerConfig['conf_options']);
    }

    private function getProducer(): Producer
    {
        return $this->producer ??= $this->kafkaFactory->createProducer($this->producerConfig['conf_options']);
    }
}
