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

use Amp\ByteStream\StreamException;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\InformationalChunk;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Component\HttpClient\Internal\AmpBodyV5;
use Symfony\Component\HttpClient\Internal\AmpClientStateV5;
use Symfony\Component\HttpClient\Internal\Canary;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Amp\delay;
use function Amp\Future\awaitFirst;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class AmpResponseV5 implements ResponseInterface, StreamableInterface
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
        private AmpClientStateV5 $multi,
        Request $request,
        array $options,
        ?LoggerInterface $logger,
    ) {
        $this->options = &$options;
        $this->logger = $logger;
        $this->timeout = $options['timeout'];
        $this->shouldBuffer = $options['buffer'];

        if ($this->inflate = \extension_loaded('zlib') && !$request->hasHeader('accept-encoding')) {
            $request->setHeader('Accept-Encoding', 'gzip');
        }

        $this->initializer = static fn (self $response) => null !== $response->options;

        $info = &$this->info;
        $headers = &$this->headers;
        $canceller = new DeferredCancellation();
        $handle = &$this->handle;

        $info['url'] = (string) $request->getUri();
        $info['http_method'] = $request->getMethod();
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
        $multi->openHandles[$id] = new DeferredFuture();
        ++$multi->responseCount;

        $this->canary = new Canary(static function () use ($canceller, $multi, $id) {
            $canceller->cancel();
            $multi->openHandles[$id]?->isComplete() || $multi->openHandles[$id]?->complete();
            unset($multi->openHandles[$id], $multi->handlesActivity[$id]);
        });

        EventLoop::queue(static function () use ($request, $multi, $id, &$info, &$headers, $canceller, &$options, $onProgress, &$handle, $logger, &$pause) {
            self::generateResponse($request, $multi, $id, $info, $headers, $canceller, $options, $onProgress, $handle, $logger, $pause);
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
            // Clear the DNS cache when all requests completed
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
     * @param AmpClientStateV5 $multi
     */
    private static function perform(ClientState $multi, ?array $responses = null): void
    {
        if ($responses) {
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
            }
        }
    }

    /**
     * @param AmpClientStateV5 $multi
     */
    private static function select(ClientState $multi, float $timeout): int
    {
        $delay = new DeferredFuture();
        $id = EventLoop::delay($timeout, $delay->complete(...));

        awaitFirst((function () use ($delay, $multi) {
            yield $delay->getFuture();

            foreach ($multi->openHandles as $deferred) {
                yield $deferred->getFuture();
            }
        })());

        if ($delay->isComplete()) {
            return 0;
        }

        $delay->complete();
        EventLoop::cancel($id);

        return 1;
    }

    private static function generateResponse(Request $request, AmpClientStateV5 $multi, string $id, array &$info, array &$headers, DeferredCancellation $canceller, array &$options, \Closure $onProgress, &$handle, ?LoggerInterface $logger, float &$pause): void
    {
        $request->setInformationalResponseHandler(static function (Response $response) use ($multi, $id, &$info, &$headers) {
            self::addResponseHeaders($response, $info, $headers);
            $multi->handlesActivity[$id][] = new InformationalChunk($response->getStatus(), $response->getHeaders());
            $multi->openHandles[$id]->complete();
            $multi->openHandles[$id] = new DeferredFuture();
        });

        try {
            if (null === $response = self::getPushedResponse($request, $multi, $info, $headers, $canceller, $options, $logger)) {
                $logger?->info(\sprintf('Request: "%s %s"', $info['http_method'], $info['url']));

                $response = self::followRedirects($request, $multi, $info, $headers, $canceller, $options, $onProgress, $handle, $logger, $pause);
            }

            $options = null;

            $multi->handlesActivity[$id][] = new FirstChunk();

            if ('HEAD' === $response->getRequest()->getMethod() || \in_array($info['http_code'], [204, 304], true)) {
                $multi->handlesActivity[$id][] = null;
                $multi->handlesActivity[$id][] = null;
                $multi->openHandles[$id]->complete();

                return;
            }

            if ($response->hasHeader('content-length')) {
                $info['download_content_length'] = (float) $response->getHeader('content-length');
            }

            $body = $response->getBody();

            while (true) {
                if (!isset($multi->openHandles[$id])) {
                    return;
                }

                $multi->openHandles[$id]->complete();
                $multi->openHandles[$id] = new DeferredFuture();

                if (0 < $pause) {
                    delay($pause, true, $canceller->getCancellation());
                }

                if (null === $data = $body->read()) {
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
    }

    private static function followRedirects(Request $originRequest, AmpClientStateV5 $multi, array &$info, array &$headers, DeferredCancellation $canceller, array $options, \Closure $onProgress, &$handle, ?LoggerInterface $logger, float &$pause): ?Response
    {
        if (0 < $pause) {
            delay($pause, true, $canceller->getCancellation());
        }

        $originRequest->setBody(new AmpBodyV5($options['body'], $info, $onProgress));
        $response = $multi->request($options, $originRequest, $canceller->getCancellation(), $info, $onProgress, $handle);
        $previousUrl = null;

        while (true) {
            self::addResponseHeaders($response, $info, $headers);
            $status = $response->getStatus();

            if (!\in_array($status, [301, 302, 303, 307, 308], true) || null === $location = $response->getHeader('location')) {
                return $response;
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
                return $response;
            }

            if (0 >= $options['max_redirects'] || $info['redirect_count'] >= $options['max_redirects']) {
                return $response;
            }

            $logger?->info(\sprintf('Redirecting: "%s %s"', $status, $info['url']));

            try {
                // Discard body of redirects
                $response->getBody()->close();
            } catch (HttpException|StreamException) {
                // Ignore streaming errors on previous responses
            }

            ++$info['redirect_count'];
            $info['url'] = $info['redirect_url'];
            $info['redirect_url'] = null;
            $previousUrl = $location;

            $request = new Request($info['url'], $info['http_method']);
            $request->setProtocolVersions($originRequest->getProtocolVersions());
            $request->setTcpConnectTimeout($originRequest->getTcpConnectTimeout());
            $request->setTlsHandshakeTimeout($originRequest->getTlsHandshakeTimeout());
            $request->setTransferTimeout($originRequest->getTransferTimeout());

            if (\in_array($status, [301, 302, 303], true)) {
                $originRequest->removeHeader('transfer-encoding');
                $originRequest->removeHeader('content-length');
                $originRequest->removeHeader('content-type');

                // Do like curl and browsers: turn POST to GET on 301, 302 and 303
                if ('POST' === $response->getRequest()->getMethod() || 303 === $status) {
                    $info['http_method'] = 'HEAD' === $response->getRequest()->getMethod() ? 'HEAD' : 'GET';
                    $request->setMethod($info['http_method']);
                }
            } else {
                $request->setBody(AmpBodyV5::rewind($response->getRequest()->getBody()));
            }

            foreach ($originRequest->getHeaderPairs() as [$name, $value]) {
                $request->addHeader($name, $value);
            }

            if ($request->getUri()->getAuthority() !== $originRequest->getUri()->getAuthority()) {
                $request->removeHeader('authorization');
                $request->removeHeader('cookie');
                $request->removeHeader('host');
            }

            if (0 < $pause) {
                delay($pause, true, $canceller->getCancellation());
            }

            $response = $multi->request($options, $request, $canceller->getCancellation(), $info, $onProgress, $handle);
            $info['redirect_time'] = microtime(true) - $info['start_time'];
        }
    }

    private static function addResponseHeaders(Response $response, array &$info, array &$headers): void
    {
        $info['http_code'] = $response->getStatus();

        if ($headers) {
            $info['debug'] .= "< \r\n";
            $headers = [];
        }

        $h = \sprintf('HTTP/%s %s %s', $response->getProtocolVersion(), $response->getStatus(), $response->getReason());
        $info['debug'] .= "< {$h}\r\n";
        $info['response_headers'][] = $h;

        foreach ($response->getHeaderPairs() as [$name, $value]) {
            $headers[strtolower($name)][] = $value;
            $h = $name.': '.$value;
            $info['debug'] .= "< {$h}\r\n";
            $info['response_headers'][] = $h;
        }

        $info['debug'] .= "< \r\n";
    }

    /**
     * Accepts pushed responses only if their headers related to authentication match the request.
     */
    private static function getPushedResponse(Request $request, AmpClientStateV5 $multi, array &$info, array &$headers, DeferredCancellation $canceller, array $options, ?LoggerInterface $logger): ?Response
    {
        if ('' !== $options['body']) {
            return null;
        }

        $authority = $request->getUri()->getAuthority();
        $cancellation = $canceller->getCancellation();

        foreach ($multi->pushedResponses[$authority] ?? [] as $i => [$pushedUrl, $pushDeferred, $pushedRequest, $pushedResponse, $parentOptions]) {
            if ($info['url'] !== $pushedUrl || $info['http_method'] !== $pushedRequest->getMethod()) {
                continue;
            }

            foreach ($parentOptions as $k => $v) {
                if ($options[$k] !== $v) {
                    continue 2;
                }
            }

            /** @var DeferredFuture $pushDeferred */
            $id = $cancellation->subscribe(static fn ($e) => $pushDeferred->error($e));

            try {
                /** @var Future $pushedResponse */
                $response = $pushedResponse->await($cancellation);
            } finally {
                $cancellation->unsubscribe($id);
            }

            foreach (['authorization', 'cookie', 'range', 'proxy-authorization'] as $k) {
                if ($response->getHeaderArray($k) !== $request->getHeaderArray($k)) {
                    continue 2;
                }
            }

            foreach ($response->getHeaderArray('vary') as $vary) {
                foreach (preg_split('/\s*+,\s*+/', $vary) as $v) {
                    if ('*' === $v || ($pushedRequest->getHeaderArray($v) !== $request->getHeaderArray($v) && 'accept-encoding' !== strtolower($v))) {
                        $logger?->debug(\sprintf('Skipping pushed response: "%s"', $info['url']));
                        continue 3;
                    }
                }
            }

            $info += [
                'connect_time' => 0.0,
                'pretransfer_time' => 0.0,
                'starttransfer_time' => 0.0,
                'total_time' => 0.0,
                'namelookup_time' => 0.0,
                'primary_ip' => '',
                'primary_port' => 0,
                'start_time' => microtime(true),
            ];

            $pushDeferred->complete();
            $logger?->debug(\sprintf('Accepting pushed response: "%s %s"', $info['http_method'], $info['url']));
            self::addResponseHeaders($response, $info, $headers);
            unset($multi->pushedResponses[$authority][$i]);

            if (!$multi->pushedResponses[$authority]) {
                unset($multi->pushedResponses[$authority]);
            }

            return $response;
        }

        return null;
    }
}
