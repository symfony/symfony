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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyResponseInterface;

/**
 * A Guzzle handler that uses Symfony's HttpClientInterface as its transport.
 *
 * This lets SDKs tightly coupled to Guzzle benefit from Symfony HttpClient's
 * features (e.g. retry logic, tracing, scoping, mocking) by plugging this
 * handler into a Guzzle client:
 *
 *   $handler = new GuzzleHttpHandler(HttpClient::create());
 *   $guzzle  = new \GuzzleHttp\Client(['handler' => $handler]);
 *
 * The handler is truly asynchronous: __invoke() returns a *pending* Promise
 * immediately without performing any I/O. The actual work is driven by
 * Symfony's HttpClientInterface::stream(), which multiplexes all in-flight
 * requests together - the same approach CurlMultiHandler takes with
 * curl_multi_*. Waiting on any single promise drives the whole pool so
 * concurrent requests benefit from parallelism automatically.
 *
 * Guzzle request options are mapped to their Symfony equivalents as faithfully
 * as possible; unsupported options are silently ignored so that existing SDK
 * option sets do not cause errors.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class GuzzleHttpHandler
{
    private readonly HttpClientInterface $client;

    /**
     * Maps each Symfony response (key) to a 3-tuple:
     *   [Psr7 RequestInterface, Guzzle options array, Guzzle Promise]
     *
     * @var \SplObjectStorage<SymfonyResponseInterface, array{0: RequestInterface, 1: array, 2: Promise}>
     */
    private readonly \SplObjectStorage $pending;

    /**
     * PSR-7 response created eagerly on the first chunk so that the same
     * instance is passed to on_headers and later resolved by the promise.
     *
     * @var \SplObjectStorage<SymfonyResponseInterface, ResponseInterface>
     */
    private readonly \SplObjectStorage $psr7Responses;

    private readonly bool $autoUpgradeHttpVersion;

    public function __construct(?HttpClientInterface $client = null, bool $autoUpgradeHttpVersion = true)
    {
        $this->client = $client ?? HttpClient::create();
        $this->autoUpgradeHttpVersion = $autoUpgradeHttpVersion;
        $this->pending = new \SplObjectStorage();
        $this->psr7Responses = new \SplObjectStorage();
    }

    /**
     * Returns a *pending* Promise - no I/O is performed here.
     *
     * The wait function passed to the Promise drives Symfony's stream() loop,
     * which resolves all currently queued requests concurrently.
     */
    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        $symfonyOptions = $this->buildSymfonyOptions($request, $options);

        try {
            $symfonyResponse = $this->client->request($request->getMethod(), (string) $request->getUri(), $symfonyOptions);
        } catch (\Exception $e) {
            // Option validation errors surface here synchronously.
            $p = new Promise();
            $p->reject($e);

            return $p;
        }

        $promise = new Promise(
            function () use ($symfonyResponse): void {
                $this->streamPending(null, $symfonyResponse);
            },
            function () use ($symfonyResponse): void {
                unset($this->pending[$symfonyResponse], $this->psr7Responses[$symfonyResponse]);
                $symfonyResponse->cancel();
            },
        );

        $this->pending[$symfonyResponse] = [$request, $options, $promise];

        if (isset($options['delay'])) {
            $pause = $symfonyResponse->getInfo('pause_handler');
            if (\is_callable($pause)) {
                $pause($options['delay'] / 1000.0);
            } else {
                usleep((int) ($options['delay'] * 1000));
            }
        }

        return $promise;
    }

    /**
     * Ticks the event loop: processes available I/O and runs queued tasks.
     *
     * @param float $timeout Maximum time in seconds to wait for network activity (0 = non-blocking)
     */
    public function tick(float $timeout = 1.0): void
    {
        $queue = PromiseUtils::queue();

        // Push streaming work onto the Guzzle task queue so that .then()
        // callbacks and other queued tasks get cooperative scheduling.
        $queue->add(fn () => $this->streamPending($timeout, true));

        $queue->run();
    }

    /**
     * Runs until all outstanding connections have completed.
     */
    public function execute(): void
    {
        while ($this->pending->count()) {
            $this->streamPending(null, false);
        }
    }

    /**
     * Performs one pass of streaming I/O over all pending responses.
     *
     * @param float|null $timeout Idle timeout passed to stream(); 0 for non-blocking, null for default
     */
    private function streamPending(?float $timeout, bool|SymfonyResponseInterface $breakAfter): void
    {
        if (!$this->pending->count()) {
            return;
        }

        $queue = PromiseUtils::queue();

        $responses = [];
        foreach ($this->pending as $r) {
            $responses[] = $r;
        }

        foreach ($this->client->stream($responses, $timeout) as $response => $chunk) {
            try {
                if ($chunk->isTimeout()) {
                    continue;
                }

                if ($chunk->isFirst()) {
                    // Deactivate 4xx/5xx exception throwing for this response;
                    // Guzzle's http_errors middleware handles that layer.
                    $response->getStatusCode();

                    [, $guzzleOpts] = $this->pending[$response] ?? [null, []];
                    $sink = $guzzleOpts['sink'] ?? null;
                    $body = Psr7Utils::streamFor(\is_string($sink) ? fopen($sink, 'w+') : ($sink ?? fopen('php://temp', 'r+')));

                    if (600 <= $response->getStatusCode()) {
                        $psrResponse = new GuzzleResponse(567, $response->getHeaders(false), $body);
                        (new \ReflectionProperty($psrResponse, 'statusCode'))->setValue($psrResponse, $response->getStatusCode());
                    } else {
                        $psrResponse = new GuzzleResponse($response->getStatusCode(), $response->getHeaders(false), $body);
                    }
                    $this->psr7Responses[$response] = $psrResponse;

                    if (isset($guzzleOpts['on_headers'])) {
                        try {
                            ($guzzleOpts['on_headers'])($psrResponse);
                        } catch (\Throwable $e) {
                            [$guzzleRequest, , $promise] = $this->pending[$response];
                            unset($this->pending[$response], $this->psr7Responses[$response]);
                            $this->fireOnStats($guzzleOpts, $guzzleRequest, $psrResponse, $e, $response);
                            $promise->reject(new RequestException($e->getMessage(), $guzzleRequest, $psrResponse, $e));

                            $response->cancel();
                        }
                    }
                }

                $content = $chunk->getContent();
                if ('' !== $content && isset($this->psr7Responses[$response])) {
                    $this->psr7Responses[$response]->getBody()->write($content);
                }

                if (!$chunk->isLast()) {
                    if (true === $breakAfter) {
                        break;
                    }
                    continue;
                }
                if (!isset($this->pending[$response])) {
                    unset($this->psr7Responses[$response]);
                } else {
                    $this->resolveResponse($response);
                }
                if (\in_array($breakAfter, [true, $response], true)) {
                    break;
                }
            } catch (TransportExceptionInterface $e) {
                if (isset($this->pending[$response])) {
                    $this->rejectResponse($response, $e);
                } else {
                    unset($this->psr7Responses[$response]);
                }
                if (\in_array($breakAfter, [true, $response], true)) {
                    break;
                }
            } finally {
                // Run .then() callbacks; they may add new entries to $this->pending.
                $queue->run();
            }
        }
    }

    private function resolveResponse(SymfonyResponseInterface $response): void
    {
        [$guzzleRequest, $options, $promise] = $this->pending[$response];
        $psrResponse = $this->psr7Responses[$response];
        unset($this->pending[$response], $this->psr7Responses[$response]);

        $body = $psrResponse->getBody();
        if ($body->isSeekable()) {
            try {
                $body->seek(0);
            } catch (\RuntimeException) {
                // ignore
            }
        }

        $this->fireOnStats($options, $guzzleRequest, $psrResponse, null, $response);
        $promise->resolve($psrResponse);
    }

    private function rejectResponse(SymfonyResponseInterface $response, TransportExceptionInterface $e): void
    {
        [$guzzleRequest, $options, $promise] = $this->pending[$response];
        $psrResponse = $this->psr7Responses[$response] ?? null;
        unset($this->pending[$response], $this->psr7Responses[$response]);

        if ($body = $psrResponse?->getBody()) {
            // Headers were already received: use RequestException so Guzzle middleware (e.g. retry)
            // can distinguish a mid-stream failure from a connection-level one.
            if ($body->isSeekable()) {
                try {
                    $body->seek(0);
                } catch (\RuntimeException) {
                    // ignore
                }
            }

            $this->fireOnStats($options, $guzzleRequest, $psrResponse, $e, $response);
            $promise->reject(new RequestException($e->getMessage(), $guzzleRequest, $psrResponse, $e));
        } else {
            // No headers received: connection-level failure.
            $this->fireOnStats($options, $guzzleRequest, null, $e, $response);
            $promise->reject(new ConnectException($e->getMessage(), $guzzleRequest, null, [], $e));
        }
    }

    private function fireOnStats(array $options, RequestInterface $request, ?ResponseInterface $psrResponse, ?\Throwable $error, SymfonyResponseInterface $symfonyResponse): void
    {
        if (!isset($options['on_stats'])) {
            return;
        }

        $handlerStats = $symfonyResponse->getInfo();
        ($options['on_stats'])(new TransferStats($request, $psrResponse, $handlerStats['total_time'] ?? 0.0, $error, $handlerStats));
    }

    private function buildSymfonyOptions(RequestInterface $request, array $guzzleOptions): array
    {
        $options = [];

        $options['headers'] = $this->extractHeaders($request, $guzzleOptions);

        $this->applyBody($request, $options);
        $this->applyAuth($guzzleOptions, $options);
        $this->applyTimeouts($guzzleOptions, $options);
        $this->applySsl($guzzleOptions, $options);
        $this->applyProxy($request, $guzzleOptions, $options);
        $this->applyRedirects($guzzleOptions, $options);
        $this->applyMisc($request, $guzzleOptions, $options);
        $this->applyDecodeContent($guzzleOptions, $options);
        if (\extension_loaded('curl') && isset($guzzleOptions['curl'])) {
            $this->applyCurlOptions($guzzleOptions['curl'], $options);
        }

        return $options;
    }

    /**
     * Merges headers from the PSR-7 request with any headers supplied via the
     * Guzzle 'headers' option (Guzzle option takes precedence).
     *
     * @return array<string, string[]>
     */
    private function extractHeaders(RequestInterface $request, array $guzzleOptions): array
    {
        $headers = $request->getHeaders();

        foreach ($guzzleOptions['headers'] ?? [] as $name => $value) {
            $headers[$name] = (array) $value;
        }

        return $headers;
    }

    private function applyBody(RequestInterface $request, array &$options): void
    {
        $key = 'content-length';
        $body = $request->getBody();
        if (!$size = $options['headers'][$key][0] ?? $options['headers'][$key = 'Content-Length'][0] ?? $body->getSize() ?? -1) {
            return;
        }

        if ($size < 0 || 1 << 21 < $size) {
            $options['body'] = static function (int $size) use ($body) {
                if ($body->isSeekable()) {
                    try {
                        $body->seek(0);
                    } catch (\RuntimeException) {
                        // ignore
                    }
                }

                while (!$body->eof()) {
                    yield $body->read($size);
                }
            };
        } else {
            if ($body->isSeekable()) {
                try {
                    $body->seek(0);
                } catch (\RuntimeException) {
                    // ignore
                }
            }
            $options['body'] = $body->getContents();
        }

        if (0 < $size) {
            $options['headers'][$key] = [$size];
        }
    }

    /**
     * Maps Guzzle's 'auth' option.
     *
     * Supported forms:
     *   ['user', 'pass']          -> auth_basic
     *   ['user', 'pass', 'basic'] -> auth_basic
     *   ['token', '', 'bearer']   -> auth_bearer
     *   ['token', '', 'token']    -> auth_bearer (alias)
     */
    private function applyAuth(array $guzzleOptions, array &$options): void
    {
        if (!isset($guzzleOptions['auth'])) {
            return;
        }

        $auth = $guzzleOptions['auth'];
        $type = strtolower($auth[2] ?? 'basic');

        if ('bearer' === $type || 'token' === $type) {
            $options['auth_bearer'] = $auth[0];
        } elseif ('ntlm' === $type) {
            array_pop($auth);
            $options['auth_ntlm'] = $auth;
        } else {
            $options['auth_basic'] = [$auth[0], $auth[1] ?? ''];
        }
    }

    private function applyTimeouts(array $guzzleOptions, array &$options): void
    {
        if (0 < ($guzzleOptions['timeout'] ?? 0)) {
            $options['max_duration'] = (float) $guzzleOptions['timeout'];
        }

        if (0 < ($guzzleOptions['read_timeout'] ?? 0)) {
            $options['timeout'] = (float) $guzzleOptions['read_timeout'];
        }

        if (0 < ($guzzleOptions['connect_timeout'] ?? 0)) {
            $options['max_connect_duration'] = (float) $guzzleOptions['connect_timeout'];
        }
    }

    /**
     * Maps SSL/TLS related options.
     *
     * Guzzle 'verify' (bool|string)  -> Symfony verify_peer / verify_host / cafile / capath
     * Guzzle 'cert'   (string|array) -> Symfony local_cert [+ passphrase]
     * Guzzle 'ssl_key'(string|array) -> Symfony local_pk   [+ passphrase]
     * Guzzle 'crypto_method'         -> Symfony crypto_method (same PHP stream constants)
     */
    private function applySsl(array $guzzleOptions, array &$options): void
    {
        if (isset($guzzleOptions['verify'])) {
            if (false === $guzzleOptions['verify']) {
                $options['verify_peer'] = false;
                $options['verify_host'] = false;
            } elseif (\is_string($guzzleOptions['verify'])) {
                if (is_dir($guzzleOptions['verify'])) {
                    $options['capath'] = $guzzleOptions['verify'];
                } else {
                    $options['cafile'] = $guzzleOptions['verify'];
                }
            }
        }

        if (isset($guzzleOptions['cert'])) {
            $cert = $guzzleOptions['cert'];
            if (\is_array($cert)) {
                [$certPath, $certPass] = $cert;
                $options['local_cert'] = $certPath;
                $options['passphrase'] = $certPass;
            } else {
                $options['local_cert'] = $cert;
            }
        }

        if (isset($guzzleOptions['ssl_key'])) {
            $key = $guzzleOptions['ssl_key'];
            if (\is_array($key)) {
                [$keyPath, $keyPass] = $key;
                $options['local_pk'] = $keyPath;
                // Do not clobber a passphrase already set by 'cert'.
                $options['passphrase'] ??= $keyPass;
            } else {
                $options['local_pk'] = $key;
            }
        }

        if (isset($guzzleOptions['crypto_method'])) {
            $options['crypto_method'] = $guzzleOptions['crypto_method'];
        }
    }

    /**
     * Maps Guzzle's 'proxy' option.
     *
     * String form -> proxy
     * Array form  -> selects proxy by URI scheme; 'no' key maps to no_proxy
     */
    private function applyProxy(RequestInterface $request, array $guzzleOptions, array &$options): void
    {
        if (!isset($guzzleOptions['proxy'])) {
            return;
        }

        if (\is_string($proxy = $guzzleOptions['proxy'])) {
            $options['proxy'] = $proxy;

            return;
        }

        $scheme = $request->getUri()->getScheme();
        if (isset($proxy[$scheme])) {
            $options['proxy'] = $proxy[$scheme];
        }

        if (isset($proxy['no'])) {
            $options['no_proxy'] = implode(',', (array) $proxy['no']);
        }
    }

    /**
     * Maps Guzzle's 'allow_redirects' to Symfony's 'max_redirects'.
     *
     * false             -> 0   (disable redirects)
     * true              -> (no override; Symfony defaults apply)
     * ['max' => N, ...] -> N
     */
    private function applyRedirects(array $guzzleOptions, array &$options): void
    {
        if (!isset($guzzleOptions['allow_redirects'])) {
            return;
        }

        if (!$ar = $guzzleOptions['allow_redirects']) {
            $options['max_redirects'] = 0;
        } elseif (\is_array($ar)) {
            // 5 matches Guzzle's own default for the 'max' sub-key.
            $options['max_redirects'] = $ar['max'] ?? 5;
        }
    }

    /**
     * Miscellaneous options that do not fit a dedicated category.
     */
    private function applyMisc(RequestInterface $request, array $guzzleOptions, array &$options): void
    {
        // We always drive I/O via stream(), so tell Symfony not to build its
        // own internal buffer - chunks are written directly to the PSR-7 response body stream.
        $options['buffer'] = false;

        if (!$this->autoUpgradeHttpVersion || '1.0' === $request->getProtocolVersion()) {
            $options['http_version'] = $request->getProtocolVersion();
        }

        // progress callback: (dlTotal, dlNow, ulTotal, ulNow) in Guzzle
        // on_progress:       (dlNow, dlTotal, info)           in Symfony
        if (isset($guzzleOptions['progress'])) {
            $guzzleProgress = $guzzleOptions['progress'];
            $options['on_progress'] = static function (int $dlNow, int $dlSize, array $info) use ($guzzleProgress): void {
                $guzzleProgress($dlSize, $dlNow, max(0, (int) ($info['upload_content_length'] ?? 0)), (int) ($info['size_upload'] ?? 0));
            };
        }
    }

    /**
     * Maps Guzzle's 'decode_content' option.
     *
     * true/string -> remove any explicit Accept-Encoding the caller set, so
     *                Symfony's HttpClient manages the header and auto-decodes
     * false       -> ensure an Accept-Encoding header is sent to disable
     *                Symfony's auto-decode behavior
     */
    private function applyDecodeContent(array $guzzleOptions, array &$options): void
    {
        if ($guzzleOptions['decode_content'] ?? true) {
            unset($options['headers']['Accept-Encoding'], $options['headers']['accept-encoding']);
        } elseif (!isset($options['headers']['Accept-Encoding']) && !isset($options['headers']['accept-encoding'])) {
            $options['headers']['Accept-Encoding'] = ['identity'];
        }
    }

    /**
     * Maps raw cURL options from Guzzle's 'curl' option bag to Symfony options.
     *
     * Constants that have a direct named Symfony equivalent are translated;
     * everything else is forwarded verbatim via CurlHttpClient's 'extra.curl'
     * pass-through so that no option is silently dropped when the underlying
     * transport happens to be CurlHttpClient.
     *
     * Options managed internally by CurlHttpClient (or Symfony's other
     * mechanisms) are silently dropped to avoid the "Cannot set X with
     * extra.curl" exception that CurlHttpClient::validateExtraCurlOptions()
     * throws for those constants.
     */
    private function applyCurlOptions(array $curlOptions, array &$options): void
    {
        // Build a set of constants that CurlHttpClient rejects in extra.curl
        // together with options whose Symfony equivalents are already applied
        // via the PSR-7 request or other Guzzle option mappings.
        static $blocked;
        $blocked ??= array_flip(array_filter([
            // Auth - handled by applyAuth() / requires NTLM-specific logic.
            \CURLOPT_HTTPAUTH, \CURLOPT_USERPWD,
            // Body - set from the PSR-7 request body by applyBody().
            \CURLOPT_READDATA, \CURLOPT_READFUNCTION, \CURLOPT_INFILESIZE,
            \CURLOPT_POSTFIELDS, \CURLOPT_UPLOAD,
            // HTTP method - taken from the PSR-7 request.
            \CURLOPT_POST, \CURLOPT_PUT, \CURLOPT_CUSTOMREQUEST,
            \CURLOPT_HTTPGET, \CURLOPT_NOBODY,
            // Headers - merged by extractHeaders().
            \CURLOPT_HTTPHEADER,
            // Internal curl signal / redirect-type flags with no Symfony equiv.
            \CURLOPT_NOSIGNAL, \CURLOPT_POSTREDIR,
            // Progress - handled by applyMisc() via Guzzle's 'progress' option.
            \CURLOPT_NOPROGRESS, \CURLOPT_PROGRESSFUNCTION,
            // Blocked by CurlHttpClient::validateExtraCurlOptions().
            \CURLOPT_PRIVATE, \CURLOPT_HEADERFUNCTION, \CURLOPT_WRITEFUNCTION,
            \CURLOPT_VERBOSE, \CURLOPT_STDERR, \CURLOPT_RETURNTRANSFER,
            \CURLOPT_URL, \CURLOPT_FOLLOWLOCATION, \CURLOPT_HEADER,
            \CURLOPT_HTTP_VERSION, \CURLOPT_PORT, \CURLOPT_DNS_USE_GLOBAL_CACHE,
            \CURLOPT_PROTOCOLS, \CURLOPT_REDIR_PROTOCOLS, \CURLOPT_COOKIEFILE,
            \CURLINFO_REDIRECT_COUNT,
            \defined('CURLOPT_HTTP09_ALLOWED') ? \CURLOPT_HTTP09_ALLOWED : null,
            \defined('CURLOPT_HEADEROPT') ? \CURLOPT_HEADEROPT : null,
            // Pinned public key: curl uses "sha256//base64" which is
            // incompatible with Symfony's peer_fingerprint array format.
            \defined('CURLOPT_PINNEDPUBLICKEY') ? \CURLOPT_PINNEDPUBLICKEY : null,
        ]));

        foreach ($curlOptions as $opt => $value) {
            if (isset($blocked[$opt])) {
                continue;
            }

            // CURLOPT_UNIX_SOCKET_PATH is conditionally defined; maps to bindto.
            if (\defined('CURLOPT_UNIX_SOCKET_PATH') && \CURLOPT_UNIX_SOCKET_PATH === $opt) {
                $options['bindto'] = $value;
                continue;
            }

            match ($opt) {
                \CURLOPT_CAINFO => $options['cafile'] = $value,
                \CURLOPT_CAPATH => $options['capath'] = $value,
                \CURLOPT_SSLCERT => $options['local_cert'] = $value,
                \CURLOPT_SSLKEY => $options['local_pk'] = $value,
                \CURLOPT_SSLCERTPASSWD,
                \CURLOPT_SSLKEYPASSWD => $options['passphrase'] = $value,
                \CURLOPT_SSL_CIPHER_LIST => $options['ciphers'] = $value,
                \CURLOPT_CERTINFO => $options['capture_peer_cert_chain'] = (bool) $value,
                \CURLOPT_PROXY => $options['proxy'] = $value,
                \CURLOPT_NOPROXY => $options['no_proxy'] = $value,
                \CURLOPT_USERAGENT => $options['headers']['User-Agent'] = [$value],
                \CURLOPT_REFERER => $options['headers']['Referer'] = [$value],
                \CURLOPT_INTERFACE => $options['bindto'] = $value,
                \CURLOPT_SSL_VERIFYPEER => $options['verify_peer'] = (bool) $value,
                \CURLOPT_SSL_VERIFYHOST => $options['verify_host'] = $value > 0,
                \CURLOPT_MAXREDIRS => $options['max_redirects'] = $value,
                \CURLOPT_TIMEOUT => $options['max_duration'] = (float) $value,
                \CURLOPT_TIMEOUT_MS => $options['max_duration'] = $value / 1000.0,
                \CURLOPT_CONNECTTIMEOUT => $options['max_connect_duration'] = (float) $value,
                \CURLOPT_CONNECTTIMEOUT_MS => $options['max_connect_duration'] = $value / 1000.0,
                default => $options['extra']['curl'][$opt] = $value,
            };
        }
    }
}
