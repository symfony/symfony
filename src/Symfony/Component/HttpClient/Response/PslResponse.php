<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Response;

use Psl\Async;
use Psl\DateTime\Duration;
use Psl\HTTP\Message;
use Psl\IO;
use Psl\URL;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\InformationalChunk;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Component\HttpClient\Internal\Canary;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Component\HttpClient\Internal\PslBody;
use Symfony\Component\HttpClient\Internal\PslClientState;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Seifeddine Gmati <azjezz@carthage.software>
 *
 * @internal
 */
final class PslResponse implements ResponseInterface, StreamableInterface
{
    use CommonResponseTrait;
    use TransportResponseTrait;

    private static string $nextId = 'a';

    private ?array $options;
    private \Closure $onProgress;

    /**
     * @internal
     */
    public function __construct(
        private PslClientState $multi,
        array $options,
        ?LoggerInterface $logger,
    ) {
        $this->options = &$options;
        $this->logger = $logger;
        $this->timeout = $options['timeout'];
        $this->shouldBuffer = $options['buffer'];

        if ($this->inflate = \extension_loaded('zlib') && !isset($options['normalized_headers']['accept-encoding'])) {
            $options['headers'][] = 'Accept-Encoding: gzip';
        }

        $this->initializer = static fn (self $response) => null !== $response->options;

        $info = &$this->info;
        $headers = &$this->headers;

        $info['url'] = (string) $options['url'];
        $info['http_method'] = $options['http_method'];
        $info['start_time'] = null;
        $info['redirect_url'] = null;
        $info['original_url'] = $info['url'];
        $info['redirect_time'] = 0.0;
        $info['redirect_count'] = 0;
        $info['size_upload'] = 0.0;
        $info['size_download'] = 0.0;
        $info['upload_content_length'] = -1.0;
        $info['download_content_length'] = -1.0;
        $info['user_data'] = $options['user_data'];
        $info['max_duration'] = $options['max_duration'];
        $info['max_connect_duration'] = $options['max_connect_duration'];
        $info['debug'] = '';

        $onProgress = $options['on_progress'] ?? static function () {};
        $onProgress = $this->onProgress = static function () use (&$info, $onProgress) {
            $info['total_time'] = microtime(true) - $info['start_time'];
            $onProgress((int) $info['size_download'], ((int) (1 + $info['download_content_length']) ?: 1) - 1, (array) $info);
        };

        $pause = 0.0;
        $this->id = $id = self::$nextId;
        self::$nextId = str_increment(self::$nextId);

        $info['pause_handler'] = static function (float $duration) use (&$pause) {
            $pause = $duration;
        };

        $multi->lastTimeout = null;
        $multi->openHandles[$id] = new Async\Deferred();
        ++$multi->responseCount;

        $this->canary = new Canary(static function () use ($multi, $id) {
            if (isset($multi->openHandles[$id])) {
                $deferred = $multi->openHandles[$id];
                if (!$deferred->isComplete()) {
                    $deferred->complete(null);
                }
            }
            unset($multi->openHandles[$id], $multi->handlesActivity[$id]);
        });

        EventLoop::queue(static function () use ($multi, $id, &$info, &$headers, &$options, $onProgress, &$pause, $logger) {
            self::generateResponse($multi, $id, $info, $headers, $options, $onProgress, $pause, $logger);
        });
    }

    public function getInfo(?string $type = null): mixed
    {
        return null !== $type ? $this->info[$type] ?? null : $this->info;
    }

    public function __serialize(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __unserialize(array $data): void
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        try {
            $this->doDestruct();
        } finally {
            if (0 >= --$this->multi->responseCount) {
                $this->multi->responseCount = 0;
                $this->multi->dnsCache = [];
            }
        }
    }

    private static function schedule(self $response, array &$runningResponses): void
    {
        if (isset($runningResponses[0])) {
            $runningResponses[0][1][$response->id] = $response;
        } else {
            $runningResponses[0] = [$response->multi, [$response->id => $response]];
        }

        if (!isset($response->multi->openHandles[$response->id])) {
            $response->multi->handlesActivity[$response->id][] = null;
            $response->multi->handlesActivity[$response->id][] = null !== $response->info['error'] ? new TransportException($response->info['error']) : null;
        }
    }

    /**
     * @param PslClientState $multi
     */
    private static function perform(ClientState $multi, ?array $responses = null): void
    {
        if ($responses) {
            $activeIds = [];
            foreach ($responses as $response) {
                try {
                    if ($response->info['start_time']) {
                        $response->info['total_time'] = microtime(true) - $response->info['start_time'];
                        ($response->onProgress)();
                    }
                } catch (\Throwable $e) {
                    $multi->handlesActivity[$response->id][] = null;
                    $multi->handlesActivity[$response->id][] = $e;
                }

                $activeIds[$response->id] = true;
            }

            foreach ($multi->handlesActivity as $id => $_) {
                if (!isset($activeIds[$id])) {
                    unset($multi->handlesActivity[$id]);
                }
            }
        }
    }

    /**
     * @param PslClientState $multi
     */
    private static function select(ClientState $multi, float $timeout): int
    {
        $delay = new Async\Deferred();
        $timerId = EventLoop::delay($timeout, static function () use ($delay): void {
            if (!$delay->isComplete()) {
                $delay->complete(null);
            }
        });

        try {
            Async\first((static function () use ($delay, $multi) {
                yield $delay->getAwaitable();

                foreach ($multi->openHandles as $deferred) {
                    yield $deferred->getAwaitable();
                }
            })());
        } catch (\Throwable) {
        }

        if ($delay->isComplete()) {
            return 0;
        }

        if (!$delay->isComplete()) {
            $delay->complete(null);
        }
        EventLoop::cancel($timerId);

        return 1;
    }

    private static function generateResponse(PslClientState $multi, string $id, array &$info, array &$headers, array &$options, \Closure $onProgress, float &$pause, ?LoggerInterface $logger): void
    {
        try {
            $logger?->info(\sprintf('Request: "%s %s"', $info['http_method'], $info['url']));

            [$transaction, $cancellation] = self::followRedirects($multi, $id, $info, $headers, $options, $onProgress, $pause, $logger);

            $options = null;

            $multi->handlesActivity[$id][] = new FirstChunk();

            if ('HEAD' === $info['http_method'] || \in_array($info['http_code'], [204, 304], true)) {
                $multi->handlesActivity[$id][] = null;
                $multi->handlesActivity[$id][] = null;
                self::signal($multi, $id);

                return;
            }

            if (null !== $contentLength = $transaction->response->headers->get('content-length')) {
                $info['download_content_length'] = (float) $contentLength;
            }

            $body = $transaction->response->body;

            while (true) {
                if (!isset($multi->openHandles[$id])) {
                    return;
                }

                self::signal($multi, $id);

                if (0 < $pause) {
                    Async\sleep(Duration::nanoseconds((int) ($pause * 1e9)));
                }

                if (null === $body || $body->reachedEndOfDataSource()) {
                    break;
                }

                $data = $body->read(65536, $cancellation);
                if ('' === $data) {
                    break;
                }

                $info['size_download'] += \strlen($data);
                $multi->handlesActivity[$id][] = $data;
            }

            $multi->handlesActivity[$id][] = null;
            $multi->handlesActivity[$id][] = null;
        } catch (\Throwable $e) {
            $multi->handlesActivity[$id][] = null;
            $multi->handlesActivity[$id][] = $e;
        } finally {
            $info['download_content_length'] = $info['size_download'];
        }

        self::signal($multi, $id);
    }

    /**
     * @return array{Message\Transaction, Async\CancellationTokenInterface}
     */
    private static function followRedirects(PslClientState $multi, string $id, array &$info, array &$headers, array &$options, \Closure $onProgress, float &$pause, ?LoggerInterface $logger): array
    {
        if (0 < $pause) {
            Async\sleep(Duration::nanoseconds((int) ($pause * 1e9)));
        }

        $cancellation = new Async\NullCancellationToken();
        $maxDuration = $options['max_duration'] ?? 0;
        if ($maxDuration > 0) {
            $cancellation = new Async\TimeoutCancellationToken(
                Duration::nanoseconds((int) ($maxDuration * 1e9)),
            );
        }

        $headerPairs = [];
        foreach ($options['headers'] as $v) {
            $h = explode(': ', $v, 2);
            if (2 === \count($h)) {
                $headerPairs[] = [$h[0], $h[1]];
            }
        }

        $body = null;
        if ('' !== $options['body']) {
            $body = new PslBody($options['body'], $info, $onProgress);
        }

        $parsedUrl = URL\parse($info['url']);

        $requestProtocol = Message\ProtocolVersion::V11;
        if ($options['http_version']) {
            $requestProtocol = match ((float) $options['http_version']) {
                1.0 => Message\ProtocolVersion::V10,
                default => Message\ProtocolVersion::V11,
            };
        }

        $request = new Message\Request(
            method: $info['http_method'],
            url: $parsedUrl,
            protocolVersion: $requestProtocol,
            headers: Message\FieldMap::from($headerPairs),
            body: $body,
        );

        $onInformationalResponse = static function (Message\Response $response) use ($multi, $id, &$info): void {
            $informationalHeaders = [];
            foreach ($response->headers as [$name, $value]) {
                $informationalHeaders[strtolower($name)][] = $value;
                $info['response_headers'][] = $name.': '.$value;
            }
            $multi->handlesActivity[$id][] = new InformationalChunk($response->status, $informationalHeaders);
            self::signal($multi, $id);
        };

        $info['debug'] .= \sprintf("> %s %s HTTP/?\r\n", $info['http_method'], $parsedUrl->path ?: '/');
        foreach ($headerPairs as [$name, $value]) {
            $info['debug'] .= "{$name}: {$value}\r\n";
        }
        $info['debug'] .= "\r\n";

        $transaction = $multi->request($options, $request, $cancellation, $info, $onProgress, $onInformationalResponse);

        $previousUrl = null;

        while (true) {
            self::addResponseHeaders($transaction, $info, $headers);
            $status = $transaction->response->status;

            if (!\in_array($status, [301, 302, 303, 307, 308], true) || null === $location = $transaction->response->headers->get('location')) {
                return [$transaction, $cancellation];
            }

            $urlResolver = new class {
                use HttpClientTrait {
                    parseUrl as public;
                    resolveUrl as public;
                }
            };

            try {
                $previousUrl ??= $urlResolver::parseUrl($info['url']);
                $location = $urlResolver::parseUrl($location);
                $location = $urlResolver::resolveUrl($location, $previousUrl);
                $info['redirect_url'] = implode('', $location);
            } catch (InvalidArgumentException) {
                return [$transaction, $cancellation];
            }

            if (0 >= $options['max_redirects'] || $info['redirect_count'] >= $options['max_redirects']) {
                return [$transaction, $cancellation];
            }

            $logger?->info(\sprintf('Redirecting: "%s %s"', $status, $info['url']));

            try {
                // discard the body.
                IO\copy($transaction->response->body, new IO\SinkWriteHandle());
            } catch (\Throwable) {
            }

            ++$info['redirect_count'];
            $info['url'] = $info['redirect_url'];
            $info['redirect_url'] = null;
            $previousUrl = $location;

            $method = $info['http_method'];
            $newBody = $body;
            $newHeaders = $headerPairs;

            if (\in_array($status, [301, 302, 303], true)) {
                $newHeaders = array_values(array_filter($newHeaders, static fn ($h) => !\in_array(strtolower($h[0]), ['transfer-encoding', 'content-length', 'content-type'], true)));

                if ('POST' === $method || 303 === $status) {
                    $info['http_method'] = 'HEAD' === $method ? 'HEAD' : 'GET';
                    $method = $info['http_method'];
                    $newBody = null;
                }
            } else {
                if ($body instanceof PslBody) {
                    $body->rewind();
                }
            }

            $newParsedUrl = URL\parse($info['url']);

            $originalAuthority = (string) $parsedUrl->authority;
            $newAuthority = (string) $newParsedUrl->authority;

            if ($newAuthority !== $originalAuthority) {
                $newHeaders = array_filter($newHeaders, static fn ($h) => !\in_array(strtolower($h[0]), ['authorization', 'cookie', 'host'], true));
            }

            if (null === $newBody) {
                $hasContentLength = false;
                foreach ($newHeaders as [$name]) {
                    if ('content-length' === strtolower($name)) {
                        $hasContentLength = true;
                        break;
                    }
                }
                if (!$hasContentLength) {
                    $newHeaders[] = ['content-length', '0'];
                }
            }

            $info['debug'] .= \sprintf("> %s %s HTTP/?\r\n", $method, $newParsedUrl->path ?: '/');
            foreach ($newHeaders as [$name, $value]) {
                $info['debug'] .= "{$name}: {$value}\r\n";
            }
            $info['debug'] .= "\r\n";

            $request = new Message\Request(
                method: $method,
                url: $newParsedUrl,
                headers: Message\FieldMap::from($newHeaders),
                body: $newBody,
            );

            if (0 < $pause) {
                Async\sleep(Duration::nanoseconds((int) ($pause * 1e9)));
            }

            $headers = [];
            $transaction = $multi->request($options, $request, $cancellation, $info, $onProgress, $onInformationalResponse);
            $info['redirect_time'] = microtime(true) - $info['start_time'];
        }
    }

    private static function addResponseHeaders(Message\Transaction $transaction, array &$info, array &$headers): void
    {
        $info['http_code'] = $transaction->response->status;

        if ($headers) {
            $info['debug'] .= "< \r\n";
            $headers = [];
        }

        $version = str_replace('HTTP/', '', $transaction->response->protocolVersion->value);
        $reason = Message\reason_phrase($transaction->response->status);
        $h = \sprintf('HTTP/%s %s%s', $version, $transaction->response->status, '' !== $reason ? ' '.$reason : '');
        $info['debug'] .= "< {$h}\r\n";
        $info['response_headers'][] = $h;

        foreach ($transaction->response->headers as [$name, $value]) {
            $headers[strtolower($name)][] = $value;
            $h = $name.': '.$value;
            $info['debug'] .= "< {$h}\r\n";
            $info['response_headers'][] = $h;
        }

        $info['debug'] .= "< \r\n";
    }

    private static function signal(PslClientState $multi, string $id): void
    {
        if (isset($multi->openHandles[$id])) {
            $deferred = $multi->openHandles[$id];
            if (!$deferred->isComplete()) {
                $deferred->complete(null);
            }
            $multi->openHandles[$id] = new Async\Deferred();
        }
    }
}
