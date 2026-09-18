<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Symfony\Component\HttpClient\Exception\HarEntryNotFoundException;
use Symfony\Component\HttpClient\Har\HarFile;
use Symfony\Component\HttpClient\Recorder\RecorderConfiguration;
use Symfony\Component\HttpClient\Recorder\RecorderConfigurationInterface;
use Symfony\Component\HttpClient\Recorder\RecorderMode;
use Symfony\Component\HttpClient\Recorder\Redactor\DefaultRedactor;
use Symfony\Component\HttpClient\Recorder\Redactor\RedactorInterface;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Records HTTP exchanges into a HAR file and replays them, driven by a RecorderConfigurationInterface.
 *
 * Requests are matched on their method, URL and body, compared with secrets masked and with the random boundary of a multipart body replaced by a fixed one.
 *
 * Only fully received responses are recorded: a response that is destroyed unread, canceled, or consumed
 * through the exception thrown for an error status is skipped, so that replaying it misses loudly instead
 * of serving an empty body. Recording an error response therefore requires consuming it without throwing,
 * with getContent(false) or toArray(false).
 */
final class RecorderHttpClient implements HttpClientInterface
{
    use AsyncDecoratorTrait {
        AsyncDecoratorTrait::withOptions insteadof HttpClientTrait;
    }
    use HttpClientTrait;

    private const MULTIPART_BOUNDARY = 'symfony-http-recorder';

    private array $defaultOptions = self::OPTIONS_DEFAULTS;
    private ?MockHttpClient $replayClient = null;
    private ?ResponseInterface $replayedResponse = null;

    public function __construct(
        HttpClientInterface $inner,
        private readonly RecorderConfigurationInterface $configuration = new RecorderConfiguration(),
        private readonly RedactorInterface $redactor = new DefaultRedactor(),
        array $defaultOptions = [],
    ) {
        $this->client = $inner;

        if ($defaultOptions) {
            [, $this->defaultOptions] = self::prepareRequest(null, null, $defaultOptions, $this->defaultOptions);
        }
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        // the caller's original options are forwarded as is in every mode, so that a client
        // decorated by this one keeps applying its own defaults; normalization only happens
        // internally, to compute the HAR key or feed the replay client
        return match ($mode = $this->configuration->getMode()) {
            RecorderMode::Passthrough => new AsyncResponse($this->client, $method, $url, $options),
            RecorderMode::Record => $this->record($method, $url, $options, true),
            RecorderMode::Replay, RecorderMode::Missing => $this->replay($method, $url, $options, RecorderMode::Missing === $mode),
        };
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);
        $clone->defaultOptions = self::mergeDefaultOptions($options, $this->defaultOptions);
        $clone->replayClient = null;

        return $clone;
    }

    /**
     * @return array{string, string, array}
     */
    private function prepare(string $method, string $url, array $options): array
    {
        [$url, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions);

        return [$method, implode('', $url), $options];
    }

    /**
     * Describes the request the way it is recorded: secrets masked and the boundary of a multipart body fixed.
     *
     * @return array{string, ?string, array<string, string[]>} The URL, the body (null when it is not a string) and the headers
     */
    private function describe(string $url, array $options): array
    {
        $headers = self::normalizeHeadersForRedactor($options['normalized_headers'] ?? []);
        $body = \is_string($options['body'] ?? null) ? $options['body'] : null;

        if (preg_match('{^multipart/[^;]++;.*?\bboundary=(?|"([^"]++)"|([^\s;]++))}i', $headers['content-type'][0] ?? '', $m, \PREG_OFFSET_CAPTURE)) {
            [$boundary, $offset] = $m[1];
            $headers['content-type'][0] = substr_replace($headers['content-type'][0], self::MULTIPART_BOUNDARY, $offset, \strlen($boundary));
            $body = null !== $body ? str_replace('--'.$boundary, '--'.self::MULTIPART_BOUNDARY, $body) : null;
        }

        return [$this->redactor->redactUrl($url), null !== $body ? $this->redactor->redactBody($body) : null, $this->redactor->redactHeaders($headers)];
    }

    /**
     * @throws HarEntryNotFoundException when no entry matches and recording what is missing is off
     */
    private function replay(string $method, string $url, array $options, bool $recordMissing): ResponseInterface
    {
        // the replay client never touches the network, so it can safely be fed the fully
        // normalized request: there is no inner client whose own defaults could be shadowed
        [, $normalizedUrl, $normalizedOptions] = $this->prepare($method, $url, $options);
        [$recordedUrl, $recordedBody] = $this->describe($normalizedUrl, $normalizedOptions);

        try {
            $response = $this->findResponse($method, $recordedUrl, $recordedBody, $recordMissing);
        } catch (HarEntryNotFoundException $e) {
            if ($recordMissing) {
                // the caller's original options, untouched, so the real request below gets them
                return $this->record($method, $url, $options, false);
            }

            $this->configuration->reportMiss($method, $recordedUrl);

            throw $e;
        }

        // clear the query option before handing the options over, because the URL
        // prepared by this client already carries it and MockHttpClient would otherwise merge it a second time
        $normalizedOptions['query'] = [];

        // The client is shared so that multiple responses can be streamed together (AsyncResponse::stream() requires all responses to share one client)
        $this->replayClient ??= new MockHttpClient(fn () => $this->replayedResponse);
        $this->replayedResponse = $response;

        try {
            return new AsyncResponse($this->replayClient, $method, $normalizedUrl, $normalizedOptions);
        } finally {
            $this->replayedResponse = null;
        }
    }

    private function findResponse(string $method, string $url, ?string $body, bool $recordMissing): ResponseInterface
    {
        if (!is_file($harFilePath = $this->configuration->getHarFilePath())) {
            throw new HarEntryNotFoundException(\sprintf('No HAR file found at "%s" for HTTP request "%s" "%s".', $harFilePath, $method, $url));
        }

        $har = HarFile::fromFile($harFilePath);

        // when recording is allowed, a request whose recorded responses were all served is a miss, so that
        // e.g. polling until a 202 turns into a 200 records every step instead of replaying the first one forever
        $index = $har->findEntryIndex($method, $url, $body, $this->configuration->getConsumedEntries(), !$recordMissing);
        $this->configuration->consumeEntry($index);

        return $har->createResponse($index);
    }

    private function record(string $method, string $url, array $options, bool $rewrite): ResponseInterface
    {
        // normalized only to compute the HAR key: the real request below keeps the caller's
        // original options, so that $this->client still applies its own default options
        [, $normalizedUrl, $normalizedOptions] = $this->prepare($method, $url, $options);
        [$recordedUrl, $requestBody, $requestHeaders] = $this->describe($normalizedUrl, $normalizedOptions);

        $buffer = '';

        return new AsyncResponse($this->client, $method, $url, $options, function (ChunkInterface $chunk, AsyncContext $context) use (&$buffer, $method, $recordedUrl, $requestBody, $requestHeaders, $rewrite): \Generator {
            if (null !== $chunk->getError()) {
                yield $chunk;

                return;
            }

            if (!$chunk->isFirst() && !$chunk->isLast()) {
                $buffer .= $chunk->getContent();
            }

            if ($chunk->isLast() && !$context->getInfo('canceled')) {
                $this->persist(HarFile::createEntry(
                    $method,
                    $recordedUrl,
                    '' !== $requestBody ? $requestBody : null,
                    $requestHeaders,
                    $context->getStatusCode(),
                    $this->redactor->redactHeaders($context->getHeaders()),
                    $this->redactor->redactBody($buffer),
                ), $rewrite);
            }

            yield $chunk;
        });
    }

    private function persist(array $entry, bool $rewrite): void
    {
        $harFilePath = $this->configuration->getHarFilePath();

        // recording rewrites the file entry by entry, while recording what is missing appends to it
        $index = $rewrite
            ? HarFile::rewrite($harFilePath, \count($this->configuration->getConsumedEntries()), $entry)
            : HarFile::update($harFilePath, static fn (HarFile $har): int => $har->addEntry($entry));

        // an entry recorded during the session is not served again to the next identical request
        $this->configuration->consumeEntry($index);
    }

    /**
     * @param array<string, list<string>> $normalizedHeaders
     *
     * @return array<string, string[]>
     */
    private static function normalizeHeadersForRedactor(array $normalizedHeaders): array
    {
        $headers = [];

        foreach ($normalizedHeaders as $name => $values) {
            foreach ($values as $value) {
                if (\is_string($value) && str_contains($value, ': ')) {
                    $headers[$name][] = substr(strstr($value, ': '), 2);
                }
            }
        }

        return $headers;
    }
}
