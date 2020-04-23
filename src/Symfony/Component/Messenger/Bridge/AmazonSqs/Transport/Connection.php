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

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

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

    /** @var ResponseInterface */
    private $currentResponse;
    /** @var array[] */
    private $buffer = [];
    /** @var string|null */
    private $queueUrl;

    public function __construct(array $configuration, HttpClientInterface $client = null)
    {
        $this->configuration = array_replace_recursive(self::DEFAULT_OPTIONS, $configuration);
        $this->client = $client ?? HttpClient::create();
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
    public static function fromDsn(string $dsn, array $options = [], HttpClientInterface $client = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Amazon SQS DSN "%s" is invalid.', $dsn));
        }

        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        $configuration = [
            'region' => $options['region'] ?? ($query['region'] ?? self::DEFAULT_OPTIONS['region']),
            'buffer_size' => $options['buffer_size'] ?? (int) ($query['buffer_size'] ?? self::DEFAULT_OPTIONS['buffer_size']),
            'wait_time' => $options['wait_time'] ?? (int) ($query['wait_time'] ?? self::DEFAULT_OPTIONS['wait_time']),
            'poll_timeout' => $options['poll_timeout'] ?? ($query['poll_timeout'] ?? self::DEFAULT_OPTIONS['poll_timeout']),
            'visibility_timeout' => $options['visibility_timeout'] ?? ($query['visibility_timeout'] ?? self::DEFAULT_OPTIONS['visibility_timeout']),
            'auto_setup' => $options['auto_setup'] ?? (bool) ($query['auto_setup'] ?? self::DEFAULT_OPTIONS['auto_setup']),
            'access_key' => $options['access_key'] ?? (urldecode($parsedUrl['user'] ?? '') ?: self::DEFAULT_OPTIONS['access_key']),
            'secret_key' => $options['secret_key'] ?? (urldecode($parsedUrl['pass'] ?? '') ?: self::DEFAULT_OPTIONS['secret_key']),
        ];

        if ('default' === ($parsedUrl['host'] ?? 'default')) {
            $configuration['endpoint'] = sprintf('https://sqs.%s.amazonaws.com', $configuration['region']);
        } else {
            $configuration['endpoint'] = sprintf('%s://%s%s', ($query['sslmode'] ?? null) === 'disable' ? 'http' : 'https', $parsedUrl['host'], ($parsedUrl['port'] ?? null) ? ':'.$parsedUrl['port'] : '');
            if (preg_match(';sqs.(.+).amazonaws.com;', $parsedUrl['host'], $matches)) {
                $configuration['region'] = $matches[1];
            }
            unset($query['sslmode']);
        }

        $parsedPath = explode('/', ltrim($parsedUrl['path'] ?? '/', '/'));
        if (\count($parsedPath) > 0) {
            $configuration['queue_name'] = end($parsedPath);
        }
        $configuration['account'] = 2 === \count($parsedPath) ? $parsedPath[0] : null;

        // check for extra keys in options
        $optionsExtraKeys = array_diff(array_keys($options), array_keys($configuration));
        if (0 < \count($optionsExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found : [%s]. Allowed options are [%s].', implode(', ', $optionsExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        // check for extra keys in options
        $queryExtraKeys = array_diff(array_keys($query), array_keys($configuration));
        if (0 < \count($queryExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found in DSN: [%s]. Allowed options are [%s].', implode(', ', $queryExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        return new self($configuration, $client);
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
            $this->currentResponse = $this->request($this->getQueueUrl(), [
                'Action' => 'ReceiveMessage',
                'VisibilityTimeout' => $this->configuration['visibility_timeout'],
                'MaxNumberOfMessages' => $this->configuration['buffer_size'],
                'MessageAttributeName.1' => 'All',
                'WaitTimeSeconds' => $this->configuration['wait_time'],
            ]);
        }

        if ($this->client->stream($this->currentResponse, $this->configuration['poll_timeout'])->current()->isTimeout()) {
            return;
        }

        $xml = new \SimpleXMLElement($this->currentResponse->getContent());
        foreach ($xml->ReceiveMessageResult->Message as $xmlMessage) {
            $headers = [];
            foreach ($xmlMessage->MessageAttribute as $item) {
                if ('String' !== (string) $item->Value->DataType) {
                    continue;
                }
                $headers[(string) $item->Name] = (string) $item->Value->StringValue;
            }
            $this->buffer[] = [
                'id' => (string) $xmlMessage->ReceiptHandle,
                'body' => (string) $xmlMessage->Body,
                'headers' => $headers,
            ];
        }

        $this->currentResponse = null;

        yield from $this->getPendingMessages();
    }

    public function setup(): void
    {
        $parameters = [
            'Action' => 'CreateQueue',
            'QueueName' => $this->configuration['queue_name'],
        ];

        if ($this->isFifoQueue($this->configuration['queue_name'])) {
            $parameters['FifoQueue'] = true;
        }

        $this->call($this->configuration['endpoint'], $parameters);
        $this->queueUrl = null;

        $this->configuration['auto_setup'] = false;
    }

    public function delete(string $id): void
    {
        $this->call($this->getQueueUrl(), [
            'Action' => 'DeleteMessage',
            'ReceiptHandle' => $id,
        ]);
    }

    public function getMessageCount(): int
    {
        $response = $this->request($this->getQueueUrl(), [
            'Action' => 'GetQueueAttributes',
            'AttributeNames' => ['ApproximateNumberOfMessages'],
        ]);
        $this->checkResponse($response);
        $xml = new \SimpleXMLElement($response->getContent());
        foreach ($xml->GetQueueAttributesResult->Attribute as $attribute) {
            if ('ApproximateNumberOfMessages' !== (string) $attribute->Name) {
                continue;
            }

            return (int) $attribute->Value;
        }

        return 0;
    }

    public function send(string $body, array $headers, int $delay = 0, ?string $messageGroupId = null, ?string $messageDeduplicationId = null): void
    {
        if ($this->configuration['auto_setup']) {
            $this->setup();
        }

        $parameters = [
            'Action' => 'SendMessage',
            'MessageBody' => $body,
            'DelaySeconds' => $delay,
        ];

        $index = 0;
        foreach ($headers as $name => $value) {
            ++$index;
            $parameters["MessageAttribute.$index.Name"] = $name;
            $parameters["MessageAttribute.$index.Value.DataType"] = 'String';
            $parameters["MessageAttribute.$index.Value.StringValue"] = $value;
        }

        if ($this->isFifoQueue($this->configuration['queue_name'])) {
            $parameters['MessageGroupId'] = null !== $messageGroupId ? $messageGroupId : __METHOD__;
            $parameters['MessageDeduplicationId'] = null !== $messageDeduplicationId ? $messageDeduplicationId : sha1(json_encode(['body' => $body, 'headers' => $headers]));
        }

        $this->call($this->getQueueUrl(), $parameters);
    }

    public function reset(): void
    {
        if (null !== $this->currentResponse) {
            $this->currentResponse->cancel();
        }

        foreach ($this->getPendingMessages() as $message) {
            $this->call($this->getQueueUrl(), [
                'Action' => 'ChangeMessageVisibility',
                'ReceiptHandle' => $message['id'],
                'VisibilityTimeout' => 0,
            ]);
        }
    }

    private function getQueueUrl(): string
    {
        if (null === $this->queueUrl) {
            $parameters = [
                'Action' => 'GetQueueUrl',
                'QueueName' => $this->configuration['queue_name'],
            ];
            if (isset($this->configuration['account'])) {
                $parameters['QueueOwnerAWSAccountId'] = $this->configuration['account'];
            }

            $response = $this->request($this->configuration['endpoint'], $parameters);
            $this->checkResponse($response);
            $xml = new \SimpleXMLElement($response->getContent());

            $this->queueUrl = (string) $xml->GetQueueUrlResult->QueueUrl;
        }

        return $this->queueUrl;
    }

    private function call(string $endpoint, array $body): void
    {
        $this->checkResponse($this->request($endpoint, $body));
    }

    private function request(string $endpoint, array $body): ResponseInterface
    {
        if (!$this->configuration['access_key']) {
            return $this->client->request('POST', $endpoint, ['body' => $body]);
        }

        $region = $this->configuration['region'];
        $service = 'sqs';

        $method = 'POST';
        $requestParameters = http_build_query($body, '', '&', PHP_QUERY_RFC1738);
        $amzDate = gmdate('Ymd\THis\Z');
        $parsedUrl = parse_url($endpoint);

        $headers = [
            'host' => $parsedUrl['host'],
            'x-amz-date' => $amzDate,
            'content-type' => 'application/x-www-form-urlencoded',
        ];

        $signedHeaders = ['host', 'x-amz-date'];
        $canonicalHeaders = implode("\n", array_map(function ($headerName) use ($headers): string {
            return sprintf('%s:%s', $headerName, $headers[$headerName]);
        }, $signedHeaders))."\n";

        $canonicalRequest = implode("\n", [
            $method,
            $parsedUrl['path'] ?? '/',
            '',
            $canonicalHeaders,
            implode(';', $signedHeaders),
            hash('sha256', $requestParameters),
        ]);

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = [gmdate('Ymd'), $region, $service, 'aws4_request'];

        $signingKey = 'AWS4'.$this->configuration['secret_key'];
        foreach ($credentialScope as $credential) {
            $signingKey = hash_hmac('sha256', $credential, $signingKey, true);
        }

        $stringToSign = implode("\n", [
            $algorithm,
            $amzDate,
            implode('/', $credentialScope),
            hash('sha256', $canonicalRequest),
        ]);

        $authorizationHeader = sprintf(
            '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $algorithm,
            $this->configuration['access_key'],
            implode('/', $credentialScope),
            implode(';', $signedHeaders),
            hash_hmac('sha256', $stringToSign, $signingKey)
        );

        $options = [
            'headers' => $headers + [
                'authorization' => $authorizationHeader,
            ],
            'body' => $requestParameters,
        ];

        return $this->client->request($method, $endpoint, $options);
    }

    private function checkResponse(ResponseInterface $response): void
    {
        if (200 !== $response->getStatusCode()) {
            $error = new \SimpleXMLElement($response->getContent(false));

            throw new TransportException($error->Error->Message);
        }
    }

    private function isFifoQueue(string $queueName): bool
    {
        return self::AWS_SQS_FIFO_SUFFIX === substr($queueName, -\strlen(self::AWS_SQS_FIFO_SUFFIX));
    }
}
