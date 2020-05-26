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
use Amp\CancellationTokenSource;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\InformationalChunk;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Component\HttpClient\Internal\AmpBody;
use Symfony\Component\HttpClient\Internal\AmpClientState;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class AmpResponse implements ResponseInterface
{
    use ResponseTrait;

    private $multi;
    private $options;
    private $canceller;
    private $onProgress;

    private static $delay;

    /**
     * @internal
     */
    public function __construct(AmpClientState $multi, Request $request, array $options, ?LoggerInterface $logger)
    {
        $this->multi = $multi;
        $this->options = &$options;
        $this->logger = $logger;
        $this->timeout = $options['timeout'];
        $this->shouldBuffer = $options['buffer'];

        if ($this->inflate = \extension_loaded('zlib') && !$request->hasHeader('accept-encoding')) {
            $request->setHeader('Accept-Encoding', 'gzip');
        }

        $this->initializer = static function (self $response) {
            return null !== $response->options;
        };

        $info = &$this->info;
        $headers = &$this->headers;
        $canceller = $this->canceller = new CancellationTokenSource();
        $handle = &$this->handle;

        $info['url'] = (string) $request->getUri();
        $info['http_method'] = $request->getMethod();
        $info['start_time'] = null;
        $info['redirect_url'] = null;
        $info['redirect_time'] = 0.0;
        $info['redirect_count'] = 0;
        $info['size_upload'] = 0.0;
        $info['size_download'] = 0.0;
        $info['upload_content_length'] = -1.0;
        $info['download_content_length'] = -1.0;
        $info['user_data'] = $options['user_data'];
        $info['debug'] = '';

        $onProgress = $options['on_progress'] ?? static function () {};
        $onProgress = $this->onProgress = static function () use (&$info, $onProgress) {
            $info['total_time'] = microtime(true) - $info['start_time'];
            $onProgress((int) $info['size_download'], ((int) (1 + $info['download_content_length']) ?: 1) - 1, (array) $info);
        };

        $this->id = $id = Loop::defer(static function () use ($request, $multi, &$id, &$info, &$headers, $canceller, &$options, $onProgress, &$handle, $logger) {
            return self::generateResponse($request, $multi, $id, $info, $headers, $canceller, $options, $onProgress, $handle, $logger);
        });

        $multi->openHandles[$id] = $id;
        ++$multi->responseCount;
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(string $type = null)
    {
        return null !== $type ? $this->info[$type] ?? null : $this->info;
    }

    public function __destruct()
    {
        try {
            $this->doDestruct();
        } finally {
            $this->close();

            // Clear the DNS cache when all requests completed
            if (0 >= --$this->multi->responseCount) {
                $this->multi->responseCount = 0;
                $this->multi->dnsCache = [];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    private function close(): void
    {
        $this->canceller->cancel();
        unset($this->multi->openHandles[$this->id], $this->multi->handlesActivity[$this->id]);
    }

    /**
     * {@inheritdoc}
     */
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
     * {@inheritdoc}
     *
     * @param AmpClientState $multi
     */
    private static function perform(ClientState $multi, array &$responses = null): void
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
     * {@inheritdoc}
     *
     * @param AmpClientState $multi
     */
    private static function select(ClientState $multi, float $timeout): int
    {
        $start = microtime(true);
        $remaining = $timeout;

        while (true) {
            self::$delay = Loop::delay(1000 * $remaining, [Loop::class, 'stop']);
            Loop::run();

            if (null === self::$delay) {
                return 1;
            }

            if (0 >= $remaining = $timeout - microtime(true) + $start) {
                return 0;
            }
        }
    }

    private static function generateResponse(Request $request, AmpClientState $multi, string $id, array &$info, array &$headers, CancellationTokenSource $canceller, array &$options, \Closure $onProgress, &$handle, ?LoggerInterface $logger)
    {
        $activity = &$multi->handlesActivity;

        $request->setInformationalResponseHandler(static function (Response $response) use (&$activity, $id, &$info, &$headers) {
            self::addResponseHeaders($response, $info, $headers);
            $activity[$id][] = new InformationalChunk($response->getStatus(), $response->getHeaders());
            self::stopLoop();
        });

        try {
            /* @var Response $response */
            if (null === $response = yield from self::getPushedResponse($request, $multi, $info, $headers, $options, $logger)) {
                $logger && $logger->info(sprintf('Request: "%s %s"', $info['http_method'], $info['url']));

                $response = yield from self::followRedirects($request, $multi, $info, $headers, $canceller, $options, $onProgress, $handle, $logger);
            }

            $options = null;

            $activity[$id][] = new FirstChunk();

            if ('HEAD' === $response->getRequest()->getMethod() || \in_array($info['http_code'], [204, 304], true)) {
                $activity[$id][] = null;
                $activity[$id][] = null;
                self::stopLoop();

                return;
            }

            if ($response->hasHeader('content-length')) {
                $info['download_content_length'] = (float) $response->getHeader('content-length');
            }

            $body = $response->getBody();

            while (true) {
                self::stopLoop();

                if (null === $data = yield $body->read()) {
                    break;
                }

                $info['size_download'] += \strlen($data);
                $activity[$id][] = $data;
            }

            $activity[$id][] = null;
            $activity[$id][] = null;
        } catch (\Throwable $e) {
            $activity[$id][] = null;
            $activity[$id][] = $e;
        } finally {
            $info['download_content_length'] = $info['size_download'];
        }

        self::stopLoop();
    }

    private static function followRedirects(Request $originRequest, AmpClientState $multi, array &$info, array &$headers, CancellationTokenSource $canceller, array $options, \Closure $onProgress, &$handle, ?LoggerInterface $logger)
    {
        $originRequest->setBody(new AmpBody($options['body'], $info, $onProgress));
        $response = yield $multi->request($options, $originRequest, $canceller->getToken(), $info, $onProgress, $handle);
        $previousUrl = null;

        while (true) {
            self::addResponseHeaders($response, $info, $headers);
            $status = $response->getStatus();

            if (!\in_array($status, [301, 302, 303, 307, 308], true) || null === $location = $response->getHeader('location')) {
                return $response;
            }

            $urlResolver = new class() {
                use HttpClientTrait {
                    parseUrl as public;
                    resolveUrl as public;
                }
            };

            try {
                $previousUrl = $previousUrl ?? $urlResolver::parseUrl($info['url']);
                $location = $urlResolver::parseUrl($location);
                $location = $urlResolver::resolveUrl($location, $previousUrl);
                $info['redirect_url'] = implode('', $location);
            } catch (InvalidArgumentException $e) {
                return $response;
            }

            if (0 >= $options['max_redirects'] || $info['redirect_count'] >= $options['max_redirects']) {
                return $response;
            }

            $logger && $logger->info(sprintf('Redirecting: "%s %s"', $status, $info['url']));

            try {
                // Discard body of redirects
                while (null !== yield $response->getBody()->read()) {
                }
            } catch (HttpException | StreamException $e) {
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
                $request->setBody(AmpBody::rewind($response->getRequest()->getBody()));
            }

            foreach ($originRequest->getRawHeaders() as [$name, $value]) {
                $request->setHeader($name, $value);
            }

            if ($request->getUri()->getAuthority() !== $originRequest->getUri()->getAuthority()) {
                $request->removeHeader('authorization');
                $request->removeHeader('cookie');
                $request->removeHeader('host');
            }

            $response = yield $multi->request($options, $request, $canceller->getToken(), $info, $onProgress, $handle);
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

        $h = sprintf('HTTP/%s %s %s', $response->getProtocolVersion(), $response->getStatus(), $response->getReason());
        $info['debug'] .= "< {$h}\r\n";
        $info['response_headers'][] = $h;

        foreach ($response->getRawHeaders() as [$name, $value]) {
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
    private static function getPushedResponse(Request $request, AmpClientState $multi, array &$info, array &$headers, array $options, ?LoggerInterface $logger)
    {
        if ('' !== $options['body']) {
            return null;
        }

        $authority = $request->getUri()->getAuthority();

        foreach ($multi->pushedResponses[$authority] ?? [] as $i => [$pushedUrl, $pushDeferred, $pushedRequest, $pushedResponse, $parentOptions]) {
            if ($info['url'] !== $pushedUrl || $info['http_method'] !== $pushedRequest->getMethod()) {
                continue;
            }

            foreach ($parentOptions as $k => $v) {
                if ($options[$k] !== $v) {
                    continue 2;
                }
            }

            foreach (['authorization', 'cookie', 'range', 'proxy-authorization'] as $k) {
                if ($pushedRequest->getHeaderArray($k) !== $request->getHeaderArray($k)) {
                    continue 2;
                }
            }

            $response = yield $pushedResponse;

            foreach ($response->getHeaderArray('vary') as $vary) {
                foreach (preg_split('/\s*+,\s*+/', $vary) as $v) {
                    if ('*' === $v || ($pushedRequest->getHeaderArray($v) !== $request->getHeaderArray($v) && 'accept-encoding' !== strtolower($v))) {
                        $logger && $logger->debug(sprintf('Skipping pushed response: "%s"', $info['url']));
                        continue 3;
                    }
                }
            }

            $pushDeferred->resolve();
            $logger && $logger->debug(sprintf('Accepting pushed response: "%s %s"', $info['http_method'], $info['url']));
            self::addResponseHeaders($response, $info, $headers);
            unset($multi->pushedResponses[$authority][$i]);

            if (!$multi->pushedResponses[$authority]) {
                unset($multi->pushedResponses[$authority]);
            }

            return $response;
        }
    }

    private static function stopLoop(): void
    {
        if (null !== self::$delay) {
            Loop::cancel(self::$delay);
            self::$delay = null;
        }

        Loop::defer([Loop::class, 'stop']);
    }
}
