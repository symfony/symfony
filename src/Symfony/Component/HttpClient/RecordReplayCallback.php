<?php

/*
 *  This file is part of the Symfony package.
 *
 *  (c) Fabien Potencier <fabien@symfony.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\ResponseRecorder;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A callback for the MockHttpClient. Three modes available:
 * - MODE_RECORD -> Make an actual HTTP request and save the response, overriding any pre-existing response.
 * - MODE_REPLAY -> Will try to replay an existing response, and throw an exception if none is found
 * - MODE_REPLAY_OR_RECORD -> Try to replay response if possible, otherwise make an actual HTTP request and save it.
 *
 * @author Gary PEGEOT <gary.pegeot@gmail.com>
 */
class RecordReplayCallback implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const MODE_RECORD = 'record';
    public const MODE_REPLAY = 'replay';
    public const MODE_REPLAY_OR_RECORD = 'replay_or_record';

    private $mode;

    private $client;

    private $recorder;

    public function __construct(ResponseRecorder $recorder, string $mode = 'replay_or_record', HttpClientInterface $client = null)
    {
        $this->recorder = $recorder;
        $this->mode = $mode;
        $this->client = $client ?? HttpClient::create();
        $this->logger = new NullLogger();
    }

    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $useHash = false;
        $ctx = hash_init('SHA512');
        $parts = [$method, $url];
        $response = null;

        if ($body = ($options['body'] ?? null)) {
            hash_update($ctx, $body);
            $useHash = true;
        }

        if (!empty($options['query'])) {
            hash_update($ctx, http_build_query($options['query']));
            $useHash = true;
        }

        foreach ($options['headers'] as $name => $values) {
            hash_update($ctx, sprintf('%s:%s', $name, implode(',', $values)));
            $useHash = true;
        }

        if ($useHash) {
            $parts[] = substr(hash_final($ctx), 0, 6);
        }

        $key = strtr(implode('-', $parts), ':/\\', '-');

        $this->log('Calculated key "{key}" for {method} request to "{url}".', [
            'key' => $key,
            'method' => $method,
            'url' => $url,
        ]);

        if (static::MODE_RECORD === $this->mode) {
            return $this->recordResponse($key, $method, $url, $options);
        }

        $replayed = $this->recorder->replay($key);

        if (null !== $replayed) {
            [$statusCode, $headers, $body] = $replayed;

            $this->log('Response replayed.');

            return new MockResponse($body, [
                'http_code' => $statusCode,
                'response_headers' => $headers,
                'user_data' => $options['user_data'] ?? null,
            ]);
        }

        if (static::MODE_REPLAY === $this->mode) {
            $this->log('Unable to replay response.');

            throw new TransportException("Unable to replay response for $method request to \"$url\" endpoint.");
        }

        return $this->recordResponse($key, $method, $url, $options);
    }

    /**
     * @return $this
     */
    public function setMode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    private function log(string $message, array $context = []): void
    {
        $context['mode'] = strtoupper($this->mode);

        $this->logger->debug("[HTTP_CLIENT][{mode}]: $message", $context);
    }

    private function recordResponse(string $key, string $method, string $url, array $options): ResponseInterface
    {
        $response = $this->client->request($method, $url, $options);
        $this->recorder->record($key, $response);

        $this->log('Response recorded.');

        return $response;
    }
}
