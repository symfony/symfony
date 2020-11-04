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

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

class ConfigurationFactory
{
    /**
     * @internal
     */
    public const DEFAULT_OPTIONS = [
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
    ];

    /**
     * Creates configuration based on the DSN and options.
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
    public static function fromDsn(string $dsn, array $options = []): array
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
            'auto_setup' => (bool) $options['auto_setup'],
            'queue_name' => (string) $options['queue_name'],
        ];

        $clientConfiguration = [
            'region' => $options['region'],
            'accessKeyId' => urldecode($parsedUrl['user'] ?? '') ?: $options['access_key'] ?? self::DEFAULT_OPTIONS['access_key'],
            'accessKeySecret' => urldecode($parsedUrl['pass'] ?? '') ?: $options['secret_key'] ?? self::DEFAULT_OPTIONS['secret_key'],
        ];
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

        return [$configuration, $clientConfiguration, $queueUrl];
    }
}
