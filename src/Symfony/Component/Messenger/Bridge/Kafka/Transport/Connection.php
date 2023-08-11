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

use RdKafka\Exception;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\TransportException;

class Connection
{
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

    /**
     * @param array{topics: list<string>, consume_timeout_ms: int, commit_async: bool, conf_options: array<string, string>} $consumerConfig
     * @param array{topic: string, poll_timeout_ms: int, flush_timeout_ms: int, conf_options: array<string, string>}        $producerConfig
     */
    private function __construct(
        private readonly array $consumerConfig,
        private readonly array $producerConfig,
        private readonly KafkaFactory $kafkaFactory,
    ) {
        if (!\extension_loaded('rdkafka')) {
            throw new LogicException(sprintf('You cannot use the "%s" as the "rdkafka" extension is not installed.', __CLASS__));
        }
    }

    public static function fromDsn(#[\SensitiveParameter] string $dsn, array $options, KafkaFactory $kafkaFactory): self
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

        if (false === $parsedUrl = parse_url($dsn)) {
            throw new \InvalidArgumentException(sprintf('The given Kafka DSN "%s" is invalid.', $dsn));
        }

        if ('kafka' !== $parsedUrl['scheme']) {
            throw new \InvalidArgumentException(sprintf('The given Kafka DSN "%s" must start with "kafka://".', $dsn));
        }

        return new self(
            self::setupConsumerOptions($parsedUrl['host'], $options['consumer'] ?? []),
            self::setupProducerOptions($parsedUrl['host'], $options['producer'] ?? []),
            $kafkaFactory,
        );
    }

    /**
     * @param array<string, bool|float|int|string|array<string>> $configOptions
     */
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
            throw new LogicException(sprintf('The "commit_async" option type must be boolean, "%s" given in the Kafka Messenger transport consumer.', \gettype($options['commit_async'])));
        }

        if (!\is_int($options['consume_timeout_ms'])) {
            throw new LogicException(sprintf('The "consume_timeout_ms" option type must be integer, "%s" given in the Kafka Messenger transport consumer.', \gettype($options['consume_timeout_ms'])));
        }

        if (!\is_array($options['topics'])) {
            throw new LogicException(sprintf('The "topics" option type must be array, "%s" given in the Kafka Messenger transport consumer.', \gettype($options['topics'])));
        }

        $options['conf_options']['metadata.broker.list'] = $brokerList;
        self::validateKafkaOptions($options['conf_options'], KafkaOption::consumer());

        if (self::REQUIRED_CONSUMER_CONF_OPTIONS !== array_intersect(self::REQUIRED_CONSUMER_CONF_OPTIONS, array_keys($options['conf_options']))) {
            throw new LogicException(sprintf('The conf_option(s) "%s" are required for the Kafka Messenger transport consumer.', implode('", "', self::REQUIRED_CONSUMER_CONF_OPTIONS)));
        }

        return $options;
    }

    /**
     * @param array<string, bool|float|int|string|array<string>> $configOptions
     */
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

    public function get(): Message
    {
        $consumer = $this->getConsumer();

        if (!$this->consumerIsSubscribed) {
            $consumer->subscribe($this->consumerConfig['topics']);
            $this->consumerIsSubscribed = true;
        }

        try {
            return $consumer->consume($this->consumerConfig['consume_timeout_ms']);
        } catch (Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function ack(Message $message): void
    {
        $consumer = $this->getConsumer();

        if ($this->consumerConfig['commit_async']) {
            $consumer->commitAsync($message);
        } else {
            $consumer->commit($message);
        }
    }

    /**
     * @param array<string, string> $headers
     */
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
