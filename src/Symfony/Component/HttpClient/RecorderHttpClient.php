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
use Symfony\Component\HttpClient\Recorder\Matcher\DefaultMatcher;
use Symfony\Component\HttpClient\Recorder\Matcher\MatcherInterface;
use Symfony\Component\HttpClient\Recorder\RecorderConfiguration;
use Symfony\Component\HttpClient\Recorder\RecorderConfigurationInterface;
use Symfony\Component\HttpClient\Recorder\RecorderMode;
use Symfony\Component\HttpClient\Recorder\Redactor\DefaultRedactor;
use Symfony\Component\HttpClient\Recorder\Redactor\RedactorInterface;
use Symfony\Component\HttpClient\Recorder\Store\StoreInterface;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Records HTTP exchanges into a HAR file and replays them, driven by a RecorderConfigurationInterface.
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

    private static array $emptyDefaults = self::OPTIONS_DEFAULTS;

    private array $defaultOptions = self::OPTIONS_DEFAULTS;
    private readonly MatcherInterface $matcher;
    private ?MockHttpClient $replayClient = null;
    private array $consumed = [];
    private ?string $consumedPath = null;

    public function __construct(
        HttpClientInterface $inner,
        private readonly StoreInterface $store,
        private readonly RecorderConfigurationInterface $configuration = new RecorderConfiguration(),
        ?MatcherInterface $matcher = null,
        private readonly RedactorInterface $redactor = new DefaultRedactor(),
        array $defaultOptions = [],
    ) {
        $this->client = $inner;
        $this->matcher = $matcher ?? new DefaultMatcher($this->redactor);

        if ($defaultOptions) {
            [, $this->defaultOptions] = self::prepareRequest(null, null, $defaultOptions, $this->defaultOptions);
        }
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        // the request is normalized for the recorded modes only, so that Passthrough stays a no-op
        return match ($this->configuration->getMode()) {
            RecorderMode::Passthrough => new AsyncResponse($this->client, $method, $url, $options),
            RecorderMode::Record => $this->record(...$this->prepare($method, $url, $options)),
            RecorderMode::Replay => $this->replay(...$this->prepare($method, $url, $options)),
        };
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);
        $clone->defaultOptions = self::mergeDefaultOptions($options, $this->defaultOptions);
        $clone->replayClient = null;
        $clone->consumed = [];
        $clone->consumedPath = null;

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
     * @throws HarEntryNotFoundException when no entry matches and recordIfMissing is off
     */
    private function replay(string $method, string $url, array $options): ResponseInterface
    {
        $harFilePath = $this->configuration->getHarFilePath();

        // clear the query option before handing the options over, because the URL
        // prepared by this client already carries it and MockHttpClient would otherwise merge it a second time
        $options['query'] = [];

        // a new file means a new playback cursor
        if ($this->consumedPath !== $harFilePath) {
            $this->consumed = [];
            $this->consumedPath = $harFilePath;
        }

        try {
            if (!is_file($harFilePath)) {
                throw new HarEntryNotFoundException(\sprintf('No HAR file found at "%s".', $harFilePath));
            }

            // The client is shared so that multiple responses can be streamed together (AsyncResponse::stream() requires all responses to share one client)
            $this->replayClient ??= new MockHttpClient(fn (string $method, string $url, array $options) => HarFile::fromFile($this->configuration->getHarFilePath())->findResponse($this->matcher, $method, $url, $options, $this->consumed));

            return new AsyncResponse($this->replayClient, $method, $url, $options);
        } catch (HarEntryNotFoundException $e) {
            if (!$this->configuration->shouldRecordIfMissing()) {
                throw $e;
            }

            return $this->record($method, $url, $options);
        }
    }

    private function record(string $method, string $url, array $options): ResponseInterface
    {
        $matchUrl = $this->redactor->redactUrl($url);
        $requestBody = \is_string($options['body']) && '' !== $options['body'] ? $this->redactor->redactBody($options['body']) : null;
        $requestHeaders = $this->redactor->redactHeaders(self::normalizeHeadersForRedactor($options['normalized_headers'] ?? []));

        $buffer = '';

        return new AsyncResponse($this->client, $method, $url, $options, function (ChunkInterface $chunk, AsyncContext $context) use (&$buffer, $method, $matchUrl, $requestBody, $requestHeaders): \Generator {
            if (null !== $chunk->getError()) {
                yield $chunk;

                return;
            }

            if (!$chunk->isFirst() && !$chunk->isLast()) {
                $buffer .= $chunk->getContent();
            }

            if ($chunk->isLast() && !$context->getInfo('canceled')) {
                $this->persist($method, $matchUrl, $requestBody, $requestHeaders, $context->getStatusCode(), $context->getHeaders(), $buffer);
            }

            yield $chunk;
        });
    }

    /**
     * @param array<string, string[]> $requestHeaders
     * @param array<string, string[]> $responseHeaders
     */
    private function persist(string $method, string $url, ?string $requestBody, array $requestHeaders, int $status, array $responseHeaders, string $content): void
    {
        $harFilePath = $this->configuration->getHarFilePath();

        $this->store->update($harFilePath, function (HarFile $har) use ($method, $url, $requestBody, $requestHeaders, $status, $responseHeaders, $content): void {
            $har->addEntry(
                $method,
                $url,
                $requestBody,
                $requestHeaders,
                $status,
                $this->redactor->redactHeaders($responseHeaders),
                $this->redactor->redactBody($content),
            );
        });
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
