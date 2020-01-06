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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\InformationalChunk;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\CurlClientState;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class CurlResponse implements ResponseInterface
{
    use ResponseTrait {
        getContent as private doGetContent;
    }

    private static $performing = false;
    private $multi;
    private $debugBuffer;

    /**
     * @internal
     */
    public function __construct(CurlClientState $multi, $ch, array $options = null, LoggerInterface $logger = null, string $method = 'GET', callable $resolveRedirect = null)
    {
        $this->multi = $multi;

        if (\is_resource($ch)) {
            $this->handle = $ch;
            $this->debugBuffer = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $this->debugBuffer);
        } else {
            $this->info['url'] = $ch;
            $ch = $this->handle;
        }

        $this->id = $id = (int) $ch;
        $this->logger = $logger;
        $this->shouldBuffer = $options['buffer'] ?? true;
        $this->timeout = $options['timeout'] ?? null;
        $this->info['http_method'] = $method;
        $this->info['user_data'] = $options['user_data'] ?? null;
        $this->info['start_time'] = $this->info['start_time'] ?? microtime(true);
        $info = &$this->info;
        $headers = &$this->headers;
        $debugBuffer = $this->debugBuffer;

        if (!$info['response_headers']) {
            // Used to keep track of what we're waiting for
            curl_setopt($ch, CURLOPT_PRIVATE, \in_array($method, ['GET', 'HEAD', 'OPTIONS', 'TRACE'], true) && 1.0 < (float) ($options['http_version'] ?? 1.1) ? 'H2' : 'H0'); // H = headers + retry counter
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($ch, string $data) use (&$info, &$headers, $options, $multi, $id, &$location, $resolveRedirect, $logger): int {
            return self::parseHeaderLine($ch, $data, $info, $headers, $options, $multi, $id, $location, $resolveRedirect, $logger);
        });

        if (null === $options) {
            // Pushed response: buffer until requested
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($ch, string $data) use ($multi, $id): int {
                $multi->handlesActivity[$id][] = $data;
                curl_pause($ch, CURLPAUSE_RECV);

                return \strlen($data);
            });

            return;
        }

        $this->inflate = !isset($options['normalized_headers']['accept-encoding']);
        curl_pause($ch, CURLPAUSE_CONT);

        if ($onProgress = $options['on_progress']) {
            $url = isset($info['url']) ? ['url' => $info['url']] : [];
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, static function ($ch, $dlSize, $dlNow) use ($onProgress, &$info, $url, $multi, $debugBuffer) {
                try {
                    rewind($debugBuffer);
                    $debug = ['debug' => stream_get_contents($debugBuffer)];
                    $onProgress($dlNow, $dlSize, $url + curl_getinfo($ch) + $info + $debug);
                } catch (\Throwable $e) {
                    $multi->handlesActivity[(int) $ch][] = null;
                    $multi->handlesActivity[(int) $ch][] = $e;

                    return 1; // Abort the request
                }

                return null;
            });
        }

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($ch, string $data) use ($multi, $id): int {
            $multi->handlesActivity[$id][] = $data;

            return \strlen($data);
        });

        $this->initializer = static function (self $response) {
            $waitFor = curl_getinfo($ch = $response->handle, CURLINFO_PRIVATE);

            return 'H' === $waitFor[0] || 'D' === $waitFor[0];
        };

        // Schedule the request in a non-blocking way
        $multi->openHandles[$id] = [$ch, $options];
        curl_multi_add_handle($multi->handle, $ch);
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(string $type = null)
    {
        if (!$info = $this->finalInfo) {
            $info = array_merge($this->info, curl_getinfo($this->handle));
            $info['url'] = $this->info['url'] ?? $info['url'];
            $info['redirect_url'] = $this->info['redirect_url'] ?? null;

            // workaround curl not subtracting the time offset for pushed responses
            if (isset($this->info['url']) && $info['start_time'] / 1000 < $info['total_time']) {
                $info['total_time'] -= $info['starttransfer_time'] ?: $info['total_time'];
                $info['starttransfer_time'] = 0.0;
            }

            rewind($this->debugBuffer);
            $info['debug'] = stream_get_contents($this->debugBuffer);
            $waitFor = curl_getinfo($this->handle, CURLINFO_PRIVATE);

            if ('H' !== $waitFor[0] && 'C' !== $waitFor[0]) {
                curl_setopt($this->handle, CURLOPT_VERBOSE, false);
                rewind($this->debugBuffer);
                ftruncate($this->debugBuffer, 0);
                $this->finalInfo = $info;
            }
        }

        return null !== $type ? $info[$type] ?? null : $info;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(bool $throw = true): string
    {
        $performing = self::$performing;
        self::$performing = $performing || '_0' === curl_getinfo($this->handle, CURLINFO_PRIVATE);

        try {
            return $this->doGetContent($throw);
        } finally {
            self::$performing = $performing;
        }
    }

    public function __destruct()
    {
        try {
            if (null === $this->timeout) {
                return; // Unused pushed response
            }

            $waitFor = curl_getinfo($this->handle, CURLINFO_PRIVATE);

            if ('C' === $waitFor[0] || '_' === $waitFor[0]) {
                $this->close();
            } elseif ('H' === $waitFor[0]) {
                $waitFor[0] = 'D'; // D = destruct
                curl_setopt($this->handle, CURLOPT_PRIVATE, $waitFor);
            }

            $this->doDestruct();
        } finally {
            $this->close();

            if (!$this->multi->openHandles) {
                // Schedule DNS cache eviction for the next request
                $this->multi->dnsCache->evictions = $this->multi->dnsCache->evictions ?: $this->multi->dnsCache->removals;
                $this->multi->dnsCache->removals = $this->multi->dnsCache->hostnames = [];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    private function close(): void
    {
        $this->inflate = null;
        unset($this->multi->openHandles[$this->id], $this->multi->handlesActivity[$this->id]);
        curl_setopt($this->handle, CURLOPT_PRIVATE, '_0');

        if (self::$performing) {
            return;
        }

        curl_multi_remove_handle($this->multi->handle, $this->handle);
        curl_setopt_array($this->handle, [
            CURLOPT_NOPROGRESS => true,
            CURLOPT_PROGRESSFUNCTION => null,
            CURLOPT_HEADERFUNCTION => null,
            CURLOPT_WRITEFUNCTION => null,
            CURLOPT_READFUNCTION => null,
            CURLOPT_INFILE => null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    private static function schedule(self $response, array &$runningResponses): void
    {
        if (isset($runningResponses[$i = (int) $response->multi->handle])) {
            $runningResponses[$i][1][$response->id] = $response;
        } else {
            $runningResponses[$i] = [$response->multi, [$response->id => $response]];
        }

        if ('_0' === curl_getinfo($ch = $response->handle, CURLINFO_PRIVATE)) {
            // Response already completed
            $response->multi->handlesActivity[$response->id][] = null;
            $response->multi->handlesActivity[$response->id][] = null !== $response->info['error'] ? new TransportException($response->info['error']) : null;
        }
    }

    /**
     * {@inheritdoc}
     */
    private static function perform(CurlClientState $multi, array &$responses = null): void
    {
        if (self::$performing) {
            if ($responses) {
                $response = current($responses);
                $multi->handlesActivity[(int) $response->handle][] = null;
                $multi->handlesActivity[(int) $response->handle][] = new TransportException(sprintf('Userland callback cannot use the client nor the response while processing "%s".', curl_getinfo($response->handle, CURLINFO_EFFECTIVE_URL)));
            }

            return;
        }

        try {
            self::$performing = true;
            $active = 0;
            while (CURLM_CALL_MULTI_PERFORM === curl_multi_exec($multi->handle, $active));

            while ($info = curl_multi_info_read($multi->handle)) {
                $result = $info['result'];
                $id = (int) $ch = $info['handle'];
                $waitFor = @curl_getinfo($ch, CURLINFO_PRIVATE) ?: '_0';

                if (\in_array($result, [\CURLE_SEND_ERROR, \CURLE_RECV_ERROR, /*CURLE_HTTP2*/ 16, /*CURLE_HTTP2_STREAM*/ 92], true) && $waitFor[1] && 'C' !== $waitFor[0]) {
                    curl_multi_remove_handle($multi->handle, $ch);
                    $waitFor[1] = (string) ((int) $waitFor[1] - 1); // decrement the retry counter
                    curl_setopt($ch, CURLOPT_PRIVATE, $waitFor);

                    if ('1' === $waitFor[1]) {
                        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                    }

                    if (0 === curl_multi_add_handle($multi->handle, $ch)) {
                        continue;
                    }
                }

                $multi->handlesActivity[$id][] = null;
                $multi->handlesActivity[$id][] = \in_array($result, [\CURLE_OK, \CURLE_TOO_MANY_REDIRECTS], true) || '_0' === $waitFor || curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD) === curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) ? null : new TransportException(sprintf('%s for "%s".', curl_strerror($result), curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)));
            }
        } finally {
            self::$performing = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    private static function select(CurlClientState $multi, float $timeout): int
    {
        if (\PHP_VERSION_ID < 70123 || (70200 <= \PHP_VERSION_ID && \PHP_VERSION_ID < 70211)) {
            // workaround https://bugs.php.net/76480
            $timeout = min($timeout, 0.01);
        }

        return curl_multi_select($multi->handle, $timeout);
    }

    /**
     * Parses header lines as curl yields them to us.
     */
    private static function parseHeaderLine($ch, string $data, array &$info, array &$headers, ?array $options, CurlClientState $multi, int $id, ?string &$location, ?callable $resolveRedirect, ?LoggerInterface $logger, &$content = null): int
    {
        $waitFor = @curl_getinfo($ch, CURLINFO_PRIVATE) ?: '_0';

        if ('H' !== $waitFor[0] && 'D' !== $waitFor[0]) {
            return \strlen($data); // Ignore HTTP trailers
        }

        if ("\r\n" !== $data) {
            // Regular header line: add it to the list
            self::addResponseHeaders([substr($data, 0, -2)], $info, $headers);

            if (0 !== strpos($data, 'HTTP/')) {
                if (0 === stripos($data, 'Location:')) {
                    $location = trim(substr($data, 9, -2));
                }

                return \strlen($data);
            }

            if (\function_exists('openssl_x509_read') && $certinfo = curl_getinfo($ch, CURLINFO_CERTINFO)) {
                $info['peer_certificate_chain'] = array_map('openssl_x509_read', array_column($certinfo, 'Cert'));
            }

            if (300 <= $info['http_code'] && $info['http_code'] < 400) {
                if (curl_getinfo($ch, CURLINFO_REDIRECT_COUNT) === $options['max_redirects']) {
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                } elseif (303 === $info['http_code'] || ('POST' === $info['http_method'] && \in_array($info['http_code'], [301, 302], true))) {
                    $info['http_method'] = 'HEAD' === $info['http_method'] ? 'HEAD' : 'GET';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
                }
            }

            return \strlen($data);
        }

        // End of headers: handle informational responses, redirects, etc.

        if (200 > $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) {
            $multi->handlesActivity[$id][] = new InformationalChunk($statusCode, $headers);
            $location = null;

            return \strlen($data);
        }

        $info['redirect_url'] = null;

        if (300 <= $statusCode && $statusCode < 400 && null !== $location) {
            if (null === $info['redirect_url'] = $resolveRedirect($ch, $location)) {
                $options['max_redirects'] = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_MAXREDIRS, $options['max_redirects']);
            } else {
                $url = parse_url($location ?? ':');

                if (isset($url['host']) && null !== $ip = $multi->dnsCache->hostnames[$url['host'] = strtolower($url['host'])] ?? null) {
                    // Populate DNS cache for redirects if needed
                    $port = $url['port'] ?? ('http' === ($url['scheme'] ?? parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), PHP_URL_SCHEME)) ? 80 : 443);
                    curl_setopt($ch, CURLOPT_RESOLVE, ["{$url['host']}:$port:$ip"]);
                    $multi->dnsCache->removals["-{$url['host']}:$port"] = "-{$url['host']}:$port";
                }
            }
        }

        if (401 === $statusCode && isset($options['auth_ntlm']) && 0 === strncasecmp($headers['www-authenticate'][0] ?? '', 'NTLM ', 5)) {
            // Continue with NTLM auth
        } elseif ($statusCode < 300 || 400 <= $statusCode || null === $location || curl_getinfo($ch, CURLINFO_REDIRECT_COUNT) === $options['max_redirects']) {
            // Headers and redirects completed, time to get the response's content
            $multi->handlesActivity[$id][] = new FirstChunk();

            if ('D' === $waitFor[0] || 'HEAD' === $info['http_method'] || \in_array($statusCode, [204, 304], true)) {
                $waitFor = '_0'; // no content expected
                $multi->handlesActivity[$id][] = null;
                $multi->handlesActivity[$id][] = null;
            } else {
                $waitFor[0] = 'C'; // C = content
            }

            curl_setopt($ch, CURLOPT_PRIVATE, $waitFor);
        } elseif (null !== $info['redirect_url'] && $logger) {
            $logger->info(sprintf('Redirecting: "%s %s"', $info['http_code'], $info['redirect_url']));
        }

        $location = null;

        return \strlen($data);
    }
}
