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
use Psr\Log\LoggerInterface;
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
    private const MESSAGE_ATTRIBUTE_NAME = 'X-Symfony-Messenger';

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
        'sslmode' => null,
        'debug' => null,
    ];

    private $configuration;
    private $client;

    /** @var ReceiveMessageResult */
    private $currentResponse;
    /** @var array[] */
    private $buffer = [];
    /** @var string|null */
    private $queueUrl;

    public function __construct(array $configuration, SqsClient $client = null, string $queueUrl = null)
    {
        $this->configuration = array_replace_recursive(self::DEFAULT_OPTIONS, $configuration);
        $this->client = $client ?? new SqsClient([]);
        $this->queueUrl = $queueUrl;
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
     * * debug: Log all HTTP requests and responses as LoggerInterface::DEBUG (Default: false)
     */
    public static function fromDsn(string $dsn, array $options = [], HttpClientInterface $client = null, LoggerInterface $logger = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Amazon SQS DSN "%s" is invalid.', $dsn));
        }

        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        // check for extra keys in options
        $optionsExtraKeys = array_diff(array_keys($options), array_keys(self::DEFAULT_OPTIONS));
        if (0 < \count($optionsExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found: [%s]. Allowed options are [%s].', implode(', ', $optionsExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        // check for extra keys in options
        $queryExtraKeys = array_diff(array_keys($query), array_keys(self::DEFAULT_OPTIONS));
        if (0 < \count($queryExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found in DSN: [%s]. Allowed options are [%s].', implode(', ', $queryExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        $options = $query + $options + self::DEFAULT_OPTIONS;
        $configuration = [
            'buffer_size' => (int) $options['buffer_size'],
            'wait_time' => (int) $options['wait_time'],
            'poll_timeout' => $options['poll_timeout'],
            'visibility_timeout' => $options['visibility_timeout'],
            'auto_setup' => filter_var($options['auto_setup'], \FILTER_VALIDATE_BOOLEAN),
            'queue_name' => (string) $options['queue_name'],
        ];

        $clientConfiguration = [
            'region' => $options['region'],
            'accessKeyId' => urldecode($parsedUrl['user'] ?? '') ?: $options['access_key'] ?? self::DEFAULT_OPTIONS['access_key'],
            'accessKeySecret' => urldecode($parsedUrl['pass'] ?? '') ?: $options['secret_key'] ?? self::DEFAULT_OPTIONS['secret_key'],
        ];
        if (isset($options['debug'])) {
            $clientConfiguration['debug'] = $options['debug'];
        }
        unset($query['region']);

        if ('default' !== ($parsedUrl['host'] ?? 'default')) {
            $clientConfiguration['endpoint'] = sprintf('%s://%s%s', ($query['sslmode'] ?? null) === 'disable' ? 'http' : 'https', $parsedUrl['host'], ($parsedUrl['port'] ?? null) ? ':'.$parsedUrl['port'] : '');
            if (preg_match(';^sqs\.([^\.]++)\.amazonaws\.com$;', $parsedUrl['host'], $matches)) {
                $clientConfiguration['region'] = $matches[1];
            }
        } elseif (self::DEFAULT_OPTIONS['endpoint'] !== $options['endpoint'] ?? self::DEFAULT_OPTIONS['endpoint']) {
            $clientConfiguration['endpoint'] = $options['endpoint'];
        }

        $parsedPath = explode('/', ltrim($parsedUrl['path'] ?? '/', '/'));
        if (\count($parsedPath) > 0 && !empty($queueName = end($parsedPath))) {
            $configuration['queue_name'] = $queueName;
        }
        $configuration['account'] = 2 === \count($parsedPath) ? $parsedPath[0] : $options['account'] ?? self::DEFAULT_OPTIONS['account'];

        // When the DNS looks like a QueueUrl, we can directly inject it in the connection
        // https://sqs.REGION.amazonaws.com/ACCOUNT/QUEUE
        $queueUrl = null;
        if (
            'https' === $parsedUrl['scheme']
            && ($parsedUrl['host'] ?? 'default') === "sqs.{$clientConfiguration['region']}.amazonaws.com"
            && ($parsedUrl['path'] ?? '/') === "/{$configuration['account']}/{$configuration['queue_name']}"
        ) {
            $queueUrl = 'https://'.$parsedUrl['host'].$parsedUrl['path'];
        }

        return new self($configuration, new SqsClient($clientConfiguration, null, $client, $logger), $queueUrl);
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
            $attributes = $message->getMessageAttributes();
            if (isset($attributes[self::MESSAGE_ATTRIBUTE_NAME]) && 'String' === $attributes[self::MESSAGE_ATTRIBUTE_NAME]->getDataType()) {
                $headers = json_decode($attributes[self::MESSAGE_ATTRIBUTE_NAME]->getStringValue(), true);
                unset($attributes[self::MESSAGE_ATTRIBUTE_NAME]);
            }
            foreach ($attributes as $name => $attribute) {
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

        $specialHeaders = [];
        foreach ($headers as $name => $value) {
            if ('.' === $name[0] || self::MESSAGE_ATTRIBUTE_NAME === $name || \strlen($name) > 256 || '.' === substr($name, -1) || 'AWS.' === substr($name, 0, \strlen('AWS.')) || 'Amazon.' === substr($name, 0, \strlen('Amazon.')) || preg_match('/([^a-zA-Z0-9_\.-]+|\.\.)/', $name)) {
                $specialHeaders[$name] = $value;

                continue;
            }

            $parameters['MessageAttributes'][$name] = new MessageAttributeValue([
                'DataType' => 'String',
                'StringValue' => $value,
            ]);
        }

        if (!empty($specialHeaders)) {
            $parameters['MessageAttributes'][self::MESSAGE_ATTRIBUTE_NAME] = new MessageAttributeValue([
                'DataType' => 'String',
                'StringValue' => json_encode($specialHeaders),
            ]);
        }

        if (self::isFifoQueue($this->configuration['queue_name'])) {
            $parameters['MessageGroupId'] = null !== $messageGroupId ? $messageGroupId : __METHOD__;
            $parameters['MessageDeduplicationId'] = null !== $messageDeduplicationId ? $messageDeduplicationId : sha1(json_encode(['body' => $body, 'headers' => $headers]));
            unset($parameters['DelaySeconds']);
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
