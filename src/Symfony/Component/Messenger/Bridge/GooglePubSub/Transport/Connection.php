<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\GooglePubSub\Transport;

use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Subscription;
use Google\Cloud\PubSub\Topic;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * @author Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 *
 * @internal
 */
class Connection
{
    private const DEFAULT_OPTIONS = [
        // PubSub client
        'apiEndpoint'    => null,
        'projectId'      => null,
        'keyFile'        => null,
        'keyFilePath'    => null,
        'requestTimeout' => null,
        'retries'        => 3,
        'scopes'         => null,
        'quotaProject'   => null,

        // Internal
        'auto_setup'     => true,
        'topic'          => null,
        'subscription'   => null,
    ];

    private $configuration;
    private $client;

    public function __construct(array $configuration, PubSubClient $client = null)
    {
        $this->configuration = array_replace_recursive(self::DEFAULT_OPTIONS, $configuration);
        $this->client        = $client ?? new PubSubClient([]);
    }

    /**
     * Creates a connection based on the DSN and options.
     *
     * Available options:
     *
     * * apiEndpoint: The hostname with optional port to use in place of the default service endpoint
     * * projectId: The project ID from the Google Developer's Console
     * * keyFile: The contents of the service account credentials.json file retrieved from the Google Developer's Console.
     * * keyFilePath: The full path to your service account credentials .json file retrieved from the Google Developers Console.
     * * requestTimeout: Seconds to wait before timing out the request. (Defaults: `0` with REST and `60` with gRPC)
     * * retries: Number of retries for a failed request. (Default: `3`)
     * * scopes: Scopes to be used for the request
     * * quotaProject: Specifies a user project to bill for access charges associated with the request
     * * topic: The name of the topic (when messages should be published)
     * * subscription: The name of the subscription (when messages should be received)
     * * auto_setup: Whether the queue should be created automatically during send / get (Default: true)
     */
    public static function fromDsn(string $dsn, array $options = []): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Google Pub/Sub DSN "%s" is invalid.', $dsn));
        }

        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        // Check for unsupported keys in options
        $optionsExtraKeys = array_diff(array_keys($options), array_keys(self::DEFAULT_OPTIONS));
        if (0 < \count($optionsExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found: [%s]. Allowed options are [%s].', implode(', ', $optionsExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        // Check for unsupported keys in query
        $queryExtraKeys = array_diff(array_keys($query), array_keys(self::DEFAULT_OPTIONS));
        if (0 < \count($queryExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found in DSN: [%s]. Allowed options are [%s].', implode(', ', $queryExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        $options       = $query + $options + self::DEFAULT_OPTIONS;
        $configuration = [
            'topic'        => $options['topic'],
            'subscription' => $options['subscription'],
            'auto_setup'   => (bool) $options['auto_setup'],
        ];

        $clientConfiguration = [
            'projectId'      => ltrim($parsedUrl['path'] ?? '', '/') ?? self::DEFAULT_OPTIONS['projectId'],
            'requestTimeout' => $options['requestTimeout'] ?? self::DEFAULT_OPTIONS['requestTimeout'],
            'retries'        => $options['retries'] ?? self::DEFAULT_OPTIONS['retries'],
            'quotaProject'   => $options['quotaProject'] ?? self::DEFAULT_OPTIONS['quotaProject'],
        ];

        // Set the service account key
        if (is_array($options['keyFile'])) {
            $clientConfiguration['keyFile'] = $options['keyFile'];
        } else {
            $clientConfiguration['keyFilePath'] = rawurldecode($parsedUrl['keyFilePath'] ?? '') ?: $options['keyFilePath'];
        }

        if (is_array($options['scopes'])) {
            $clientConfiguration['scopes'] = $options['scopes'];
        }

        // Set the optional api endpoint (hostname+port)
        if ('default' !== ($parsedUrl['host'] ?? 'default')) {
            $clientConfiguration['apiEndpoint'] = sprintf('%s%s', $parsedUrl['host'], ($parsedUrl['port'] ?? null) ? ':' . $parsedUrl['port'] : '');
        }

        return new self($configuration, new PubSubClient($clientConfiguration));
    }

    public function setupTopic(): ?Topic
    {
        if (null === $this->configuration['topic']) {
            return null;
        }

        $topic = $this->client->topic($this->configuration['topic']);
        if ($topic->exists()) {
            return $topic;
        }

        if (!$this->configuration['auto_setup']) {
            return null;
        }

        try {
            $topic->create();
        } catch (GoogleException $e) {
            throw new TransportException(sprintf('Failed to create the Google Pub/Sub topic "%s".', $this->configuration['topic']), 0, $e);
        }

        return $topic;
    }

    public function setupSubscription(): ?Subscription
    {
        if (null === $this->configuration['subscription']) {
            return null;
        }

        $subscription = $this->client->subscription($this->configuration['subscription']);
        if ($subscription->exists()) {
            return $subscription;
        }

        if (!$this->configuration['auto_setup']) {
            return null;
        }

        try {
            $subscription->create();
        } catch (GoogleException $e) {
            throw new TransportException(sprintf('Failed to create the Google Pub/Sub subscription "%s".', $this->configuration['subscription']), 0, $e);
        }

        return $subscription;
    }
}
