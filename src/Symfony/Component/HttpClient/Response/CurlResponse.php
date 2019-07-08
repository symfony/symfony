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
    use ResponseTrait;

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
        $this->timeout = $options['timeout'] ?? null;
        $this->info['http_method'] = $method;
        $this->info['user_data'] = $options['user_data'] ?? null;
        $this->info['start_time'] = $this->info['start_time'] ?? microtime(true);
        $info = &$this->info;
        $headers = &$this->headers;
        $debugBuffer = $this->debugBuffer;

        if (!$info['response_headers']) {
            // Used to keep track of what we're waiting for
            curl_setopt($ch, CURLOPT_PRIVATE, 'headers');
        }

        if (null === $content = &$this->content) {
            $content = ($options['buffer'] ?? true) ? fopen('php://temp', 'w+') : null;
        } else {
            // Move the pushed response to the activity list
            if (ftell($content)) {
                rewind($content);
                $multi->handlesActivity[$id][] = stream_get_contents($content);
            }
            $content = ($options['buffer'] ?? true) ? $content : null;
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($ch, string $data) use (&$info, &$headers, $options, $multi, $id, &$location, $resolveRedirect, $logger): int {
            return self::parseHeaderLine($ch, $data, $info, $headers, $options, $multi, $id, $location, $resolveRedirect, $logger);
        });

        if (null === $options) {
            // Pushed response: buffer until requested
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($ch, string $data) use (&$content): int {
                return fwrite($content, $data);
            });

            return;
        }

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
            });
        }

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($ch, string $data) use (&$content, $multi, $id): int {
            $multi->handlesActivity[$id][] = $data;

            return null !== $content ? fwrite($content, $data) : \strlen($data);
        });

        $this->initializer = static function (self $response) {
            if (null !== $response->info['error']) {
                throw new TransportException($response->info['error']);
            }

            $waitFor = curl_getinfo($ch = $response->handle, CURLINFO_PRIVATE);

            if (\in_array($waitFor, ['headers', 'destruct'], true)) {
                try {
                    if (\defined('CURLOPT_STREAM_WEIGHT')) {
                        curl_setopt($ch, CURLOPT_STREAM_WEIGHT, 32);
                    }
                    self::stream([$response])->current();
                } catch (\Throwable $e) {
                    // Persist timeouts thrown during initialization
                    $response->info['error'] = $e->getMessage();
                    $response->close();
                    throw $e;
                }
            } elseif ('content' === $waitFor && ($response->multi->handlesActivity[$response->id][0] ?? null) instanceof FirstChunk) {
                self::stream([$response])->current();
            }

            curl_setopt($ch, CURLOPT_HEADERFUNCTION, null);
            curl_setopt($ch, CURLOPT_READFUNCTION, null);
            curl_setopt($ch, CURLOPT_INFILE, null);
        };

        // Schedule the request in a non-blocking way
        $multi->openHandles[$id] = $ch;
        curl_multi_add_handle($multi->handle, $ch);
        self::perform($multi);
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(string $type = null)
    {
        if (!$info = $this->finalInfo) {
            self::perform($this->multi);

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

            if (!\in_array(curl_getinfo($this->handle, CURLINFO_PRIVATE), ['headers', 'content'], true)) {
                curl_setopt($this->handle, CURLOPT_VERBOSE, false);
                rewind($this->debugBuffer);
                ftruncate($this->debugBuffer, 0);
                $this->finalInfo = $info;
            }
        }

        return null !== $type ? $info[$type] ?? null : $info;
    }

    public function __destruct()
    {
        try {
            if (null === $this->timeout) {
                return; // Unused pushed response
            }

            if ('content' === $waitFor = curl_getinfo($this->handle, CURLINFO_PRIVATE)) {
                $this->close();
            } elseif ('headers' === $waitFor) {
                curl_setopt($this->handle, CURLOPT_PRIVATE, 'destruct');
            }

            $this->doDestruct();
        } finally {
            $this->close();

            // Clear local caches when the only remaining handles are about pushed responses
            if (!$this->multi->openHandles) {
                if ($this->logger) {
                    foreach ($this->multi->pushedResponses as $url => $response) {
                        $this->logger->debug(sprintf('Unused pushed response: "%s"', $url));
                    }
                }

                $this->multi->pushedResponses = [];
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
        unset($this->multi->openHandles[$this->id], $this->multi->handlesActivity[$this->id]);
        curl_multi_remove_handle($this->multi->handle, $this->handle);
        curl_setopt_array($this->handle, [
            CURLOPT_PRIVATE => '',
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

        if ('' === curl_getinfo($ch = $response->handle, CURLINFO_PRIVATE)) {
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
            return;
        }

        try {
            self::$performing = true;
            while (CURLM_CALL_MULTI_PERFORM === curl_multi_exec($multi->handle, $active));

            while ($info = curl_multi_info_read($multi->handle)) {
                $multi->handlesActivity[(int) $info['handle']][] = null;
                $multi->handlesActivity[(int) $info['handle']][] = \in_array($info['result'], [\CURLE_OK, \CURLE_TOO_MANY_REDIRECTS], true) || (\CURLE_WRITE_ERROR === $info['result'] && 'destruct' === @curl_getinfo($info['handle'], CURLINFO_PRIVATE)) ? null : new TransportException(sprintf('%s for "%s".', curl_strerror($info['result']), curl_getinfo($info['handle'], CURLINFO_EFFECTIVE_URL)));
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
        return curl_multi_select($multi->handle, $timeout);
    }

    /**
     * Parses header lines as curl yields them to us.
     */
    private static function parseHeaderLine($ch, string $data, array &$info, array &$headers, ?array $options, CurlClientState $multi, int $id, ?string &$location, ?callable $resolveRedirect, ?LoggerInterface $logger): int
    {
        if (!\in_array($waitFor = @curl_getinfo($ch, CURLINFO_PRIVATE), ['headers', 'destruct'], true)) {
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

        // End of headers: handle redirects and add to the activity list
        if (200 > $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) {
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

        $location = null;

        if ($statusCode < 300 || 400 <= $statusCode || curl_getinfo($ch, CURLINFO_REDIRECT_COUNT) === $options['max_redirects']) {
            // Headers and redirects completed, time to get the response's body
            $multi->handlesActivity[$id] = [new FirstChunk()];

            if ('destruct' === $waitFor) {
                return 0;
            }

            curl_setopt($ch, CURLOPT_PRIVATE, 'content');
        } elseif (null !== $info['redirect_url'] && $logger) {
            $logger->info(sprintf('Redirecting: "%s %s"', $info['http_code'], $info['redirect_url']));
        }

        return \strlen($data);
    }
}
