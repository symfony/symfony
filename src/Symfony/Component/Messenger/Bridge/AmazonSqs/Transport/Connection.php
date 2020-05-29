<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Transport;

use AsyncAws\Sqs\Enum\QueueAttributeName;
use AsyncAws\Sqs\Result\ReceiveMessageResult;
use AsyncAws\Sqs\SqsClient;
use AsyncAws\Sqs\ValueObject\MessageAttributeValue;
use Symfony\Component\Dsn\DsnParser;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A SQS connection.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @internal
 * @final
 */
class Connection
{
    private const AWS_SQS_FIFO_SUFFIX = '.fifo';

    private const DEFAULT_OPTIONS = [
        'buffer_size' => 9,
        'wait_time' => 20,
        'poll_timeout' => 0.1,
        'visibility_timeout' => null,
        'auto_setup' => true,
        'access_key' => null,
        'secret_key' => null,
        'endpoint' => 'https://sqs.eu-west-1.amazonaws.com',
        'region' => 'eu-west-1',
        'queue_name' => 'messages',
        'account' => null,
    ];

    private $configuration;
    private $client;

    /** @var ReceiveMessageResult */
    private $currentResponse;
    /** @var array[] */
    private $buffer = [];
    /** @var string|null */
    private $queueUrl;

    public function __construct(array $configuration, SqsClient $client = null)
    {
        $this->configuration = array_replace_recursive(self::DEFAULT_OPTIONS, $configuration);
        $this->client = $client ?? new SqsClient([]);
    }

    public function __destruct()
    {
        $this->reset();
    }

    /**
     * Creates a connection based on the DSN and options.
     *
     * Available options:
     *
     * * endpoint: absolute URL to the SQS service (Default: https://sqs.eu-west-1.amazonaws.com)
     * * region: name of the AWS region (Default: eu-west-1)
     * * queue_name: name of the queue (Default: messages)
     * * account: identifier of the AWS account
     * * access_key: AWS access key
     * * secret_key: AWS secret key
     * * buffer_size: number of messages to prefetch (Default: 9)
     * * wait_time: long polling duration in seconds (Default: 20)
     * * poll_timeout: amount of seconds the transport should wait for new message
     * * visibility_timeout: amount of seconds the message won't be visible
     * * auto_setup: Whether the queue should be created automatically during send / get (Default: true)
     */
    public static function fromDsn(string $dsnString, array $options = [], HttpClientInterface $client = null): self
    {
        $dsn = DsnParser::parse($dsnString);
        $query = $dsn->getParameters();

        $configuration = [
            'buffer_size' => $options['buffer_size'] ?? (int) ($query['buffer_size'] ?? self::DEFAULT_OPTIONS['buffer_size']),
            'wait_time' => $options['wait_time'] ?? (int) ($query['wait_time'] ?? self::DEFAULT_OPTIONS['wait_time']),
            'poll_timeout' => $options['poll_timeout'] ?? ($query['poll_timeout'] ?? self::DEFAULT_OPTIONS['poll_timeout']),
            'visibility_timeout' => $options['visibility_timeout'] ?? ($query['visibility_timeout'] ?? self::DEFAULT_OPTIONS['visibility_timeout']),
            'auto_setup' => $options['auto_setup'] ?? (bool) ($query['auto_setup'] ?? self::DEFAULT_OPTIONS['auto_setup']),
        ];

        $clientConfiguration = [
            'region' => $options['region'] ?? ($query['region'] ?? self::DEFAULT_OPTIONS['region']),
            'accessKeyId' => $options['access_key'] ?? ($dsn->getUser() ?: self::DEFAULT_OPTIONS['access_key']),
            'accessKeySecret' => $options['secret_key'] ?? ($dsn->getPassword() ?: self::DEFAULT_OPTIONS['secret_key']),
        ];
        unset($query['region']);

        if (null !== $dsn->getHost()) {
            $clientConfiguration['endpoint'] = sprintf('%s://%s%s', ($query['sslmode'] ?? null) === 'disable' ? 'http' : 'https', $dsn->getHost(), ($dsn->getPort() ? ':'.$dsn->getPort() : ''));
            if (preg_match(';^sqs\.([^\.]++)\.amazonaws\.com$;', $dsn->getHost(), $matches)) {
                $clientConfiguration['region'] = $matches[1];
            }
            unset($query['sslmode']);
        }

        $parsedPath = explode('/', ltrim($dsn->getPath() ?? '/', '/'));
        if (\count($parsedPath) > 0) {
            $configuration['queue_name'] = end($parsedPath);
        }
        $configuration['account'] = 2 === \count($parsedPath) ? $parsedPath[0] : null;

        // check for extra keys in options
        $optionsExtraKeys = array_diff(array_keys($options), array_keys(self::DEFAULT_OPTIONS));
        if (0 < \count($optionsExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found : [%s]. Allowed options are [%s].', implode(', ', $optionsExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        // check for extra keys in options
        $queryExtraKeys = array_diff(array_keys($query), array_keys(self::DEFAULT_OPTIONS));
        if (0 < \count($queryExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found in DSN: [%s]. Allowed options are [%s].', implode(', ', $queryExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        return new self($configuration, new SqsClient($clientConfiguration, null, $client));
    }

    public function get(): ?array
    {
        if ($this->configuration['auto_setup']) {
            $this->setup();
        }

        foreach ($this->getNextMessages() as $message) {
            return $message;
        }

        return null;
    }

    /**
     * @return array[]
     */
    private function getNextMessages(): \Generator
    {
        yield from $this->getPendingMessages();
        yield from $this->getNewMessages();
    }

    /**
     * @return array[]
     */
    private function getPendingMessages(): \Generator
    {
        while (!empty($this->buffer)) {
            yield array_shift($this->buffer);
        }
    }

    /**
     * @return array[]
     */
    private function getNewMessages(): \Generator
    {
        if (null === $this->currentResponse) {
            $this->currentResponse = $this->client->receiveMessage([
                'QueueUrl' => $this->getQueueUrl(),
                'VisibilityTimeout' => $this->configuration['visibility_timeout'],
                'MaxNumberOfMessages' => $this->configuration['buffer_size'],
                'MessageAttributeNames' => ['All'],
                'WaitTimeSeconds' => $this->configuration['wait_time'],
            ]);
        }

        if (!$this->fetchMessage()) {
            return;
        }

        yield from $this->getPendingMessages();
    }

    private function fetchMessage(): bool
    {
        if (!$this->currentResponse->resolve($this->configuration['poll_timeout'])) {
            return false;
        }

        foreach ($this->currentResponse->getMessages() as $message) {
            $headers = [];
            foreach ($message->getMessageAttributes() as $name => $attribute) {
                if ('String' !== $attribute->getDataType()) {
                    continue;
                }

                $headers[$name] = $attribute->getStringValue();
            }

            $this->buffer[] = [
                'id' => $message->getReceiptHandle(),
                'body' => $message->getBody(),
                'headers' => $headers,
            ];
        }

        $this->currentResponse = null;

        return true;
    }

    public function setup(): void
    {
        // Set to false to disable setup more than once
        $this->configuration['auto_setup'] = false;
        if ($this->client->queueExists([
            'QueueName' => $this->configuration['queue_name'],
            'QueueOwnerAWSAccountId' => $this->configuration['account'],
        ])->isSuccess()) {
            return;
        }

        if (null !== $this->configuration['account']) {
            throw new InvalidArgumentException(sprintf('The Amazon SQS queue "%s" does not exists (or you don\'t have permissions on it), and can\'t be created when an account is provided.', $this->configuration['queue_name']));
        }

        $parameters = ['QueueName' => $this->configuration['queue_name']];

        if (self::isFifoQueue($this->configuration['queue_name'])) {
            $parameters['FifoQueue'] = true;
        }

        $this->client->createQueue($parameters);
        $exists = $this->client->queueExists(['QueueName' => $this->configuration['queue_name']]);
        // Blocking call to wait for the queue to be created
        $exists->wait();
        if (!$exists->isSuccess()) {
            throw new TransportException(sprintf('Failed to crate the Amazon SQS queue "%s".', $this->configuration['queue_name']));
        }
        $this->queueUrl = null;
    }

    public function delete(string $id): void
    {
        $this->client->deleteMessage([
            'QueueUrl' => $this->getQueueUrl(),
            'ReceiptHandle' => $id,
        ]);
    }

    public function getMessageCount(): int
    {
        $response = $this->client->getQueueAttributes([
            'QueueUrl' => $this->getQueueUrl(),
            'AttributeNames' => [QueueAttributeName::APPROXIMATE_NUMBER_OF_MESSAGES],
        ]);

        $attributes = $response->getAttributes();

        return (int) ($attributes[QueueAttributeName::APPROXIMATE_NUMBER_OF_MESSAGES] ?? 0);
    }

    public function send(string $body, array $headers, int $delay = 0, ?string $messageGroupId = null, ?string $messageDeduplicationId = null): void
    {
        if ($this->configuration['auto_setup']) {
            $this->setup();
        }

        $parameters = [
            'QueueUrl' => $this->getQueueUrl(),
            'MessageBody' => $body,
            'DelaySeconds' => $delay,
            'MessageAttributes' => [],
        ];

        foreach ($headers as $name => $value) {
            $parameters['MessageAttributes'][$name] = new MessageAttributeValue([
                'DataType' => 'String',
                'StringValue' => $value,
            ]);
        }

        if (self::isFifoQueue($this->configuration['queue_name'])) {
            $parameters['MessageGroupId'] = null !== $messageGroupId ? $messageGroupId : __METHOD__;
            $parameters['MessageDeduplicationId'] = null !== $messageDeduplicationId ? $messageDeduplicationId : sha1(json_encode(['body' => $body, 'headers' => $headers]));
        }

        $this->client->sendMessage($parameters);
    }

    public function reset(): void
    {
        if (null !== $this->currentResponse) {
            // fetch current response in order to requeue in transit messages
            if (!$this->fetchMessage()) {
                $this->currentResponse->cancel();
                $this->currentResponse = null;
            }
        }

        foreach ($this->getPendingMessages() as $message) {
            $this->client->changeMessageVisibility([
                'QueueUrl' => $this->getQueueUrl(),
                'ReceiptHandle' => $message['id'],
                'VisibilityTimeout' => 0,
            ]);
        }
    }

    private function getQueueUrl(): string
    {
        if (null !== $this->queueUrl) {
            return $this->queueUrl;
        }

        return $this->queueUrl = $this->client->getQueueUrl([
            'QueueName' => $this->configuration['queue_name'],
            'QueueOwnerAWSAccountId' => $this->configuration['account'],
        ])->getQueueUrl();
    }

    private static function isFifoQueue(string $queueName): bool
    {
        return self::AWS_SQS_FIFO_SUFFIX === substr($queueName, -\strlen(self::AWS_SQS_FIFO_SUFFIX));
    }
}
