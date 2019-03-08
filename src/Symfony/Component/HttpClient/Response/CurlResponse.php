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

use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Exception\TransportException;
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

    /**
     * @internal
     */
    public function __construct(\stdClass $multi, $ch, array $options = null, string $method = 'GET', callable $resolveRedirect = null)
    {
        $this->multi = $multi;

        if (\is_resource($ch)) {
            $this->handle = $ch;
        } else {
            $this->info['url'] = $ch;
            $ch = $this->handle;
        }

        $this->id = $id = (int) $ch;
        $this->timeout = $options['timeout'] ?? null;
        $this->info['http_method'] = $method;
        $this->info['user_data'] = $options['user_data'] ?? null;
        $this->info['start_time'] = $this->info['start_time'] ?? microtime(true);
        $info = &$this->info;
        $headers = &$this->headers;

        if (!$info['raw_headers']) {
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

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($ch, string $data) use (&$info, &$headers, $options, $multi, $id, &$location, $resolveRedirect): int {
            return self::parseHeaderLine($ch, $data, $info, $headers, $options, $multi, $id, $location, $resolveRedirect);
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
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, static function ($ch, $dlSize, $dlNow) use ($onProgress, &$info, $url) {
                try {
                    $onProgress($dlNow, $dlSize, $url + curl_getinfo($ch) + $info);
                } catch (\Throwable $e) {
                    $info['error'] = $e;

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

            if (\in_array(curl_getinfo($ch = $response->handle, CURLINFO_PRIVATE), ['headers', 'destruct'], true)) {
                try {
                    if (\defined('CURLOPT_STREAM_WEIGHT')) {
                        curl_setopt($ch, CURLOPT_STREAM_WEIGHT, 32);
                    }
                    self::stream([$response])->current();
                } catch (\Throwable $e) {
                    $response->info['error'] = $e->getMessage();
                    $response->close();
                    throw $e;
                }
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

            if (!\in_array(curl_getinfo($this->handle, CURLINFO_PRIVATE), ['headers', 'content'], true)) {
                $this->finalInfo = $info;
            }
        }

        return null !== $type ? $info[$type] ?? null : $info;
    }

    public function __destruct()
    {
        try {
            if (null === $this->timeout || isset($this->info['url'])) {
                return; // pushed response
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
            if (\count($this->multi->openHandles) === \count($this->multi->pushedResponses)) {
                $this->multi->pushedResponses = [];
                // Schedule DNS cache eviction for the next request
                $this->multi->dnsCache[2] = $this->multi->dnsCache[2] ?: $this->multi->dnsCache[1];
                $this->multi->dnsCache[1] = $this->multi->dnsCache[0] = [];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function close(): void
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
    protected static function schedule(self $response, array &$runningResponses): void
    {
        if ('' === curl_getinfo($ch = $response->handle, CURLINFO_PRIVATE)) {
            // no-op - response already completed
        } elseif (isset($runningResponses[$i = (int) $response->multi->handle])) {
            $runningResponses[$i][1][$response->id] = $response;
        } else {
            $runningResponses[$i] = [$response->multi, [$response->id => $response]];
        }
    }

    /**
     * {@inheritdoc}
     */
    protected static function perform(\stdClass $multi, array &$responses = null): void
    {
        if (self::$performing) {
            return;
        }

        try {
            self::$performing = true;
            while (CURLM_CALL_MULTI_PERFORM === curl_multi_exec($multi->handle, $active));

            while ($info = curl_multi_info_read($multi->handle)) {
                $multi->handlesActivity[(int) $info['handle']][] = null;
                $multi->handlesActivity[(int) $info['handle']][] = \in_array($info['result'], [\CURLE_OK, \CURLE_TOO_MANY_REDIRECTS], true) ? null : new TransportException(curl_error($info['handle']));
            }
        } finally {
            self::$performing = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected static function select(\stdClass $multi, float $timeout): int
    {
        return curl_multi_select($multi->handle, $timeout);
    }

    /**
     * Parses header lines as curl yields them to us.
     */
    private static function parseHeaderLine($ch, string $data, array &$info, array &$headers, ?array $options, \stdClass $multi, int $id, ?string &$location, ?callable $resolveRedirect): int
    {
        if (!\in_array($waitFor = @curl_getinfo($ch, CURLINFO_PRIVATE), ['headers', 'destruct'], true)) {
            return \strlen($data); // Ignore HTTP trailers
        }

        if ("\r\n" !== $data) {
            // Regular header line: add it to the list
            self::addRawHeaders([substr($data, 0, -2)], $info, $headers);

            if (0 === strpos($data, 'HTTP') && 300 <= $info['http_code'] && $info['http_code'] < 400) {
                if (curl_getinfo($ch, CURLINFO_REDIRECT_COUNT) === $options['max_redirects']) {
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                } elseif (303 === $info['http_code'] || ('POST' === $info['http_method'] && \in_array($info['http_code'], [301, 302], true))) {
                    $info['http_method'] = 'HEAD' === $info['http_method'] ? 'HEAD' : 'GET';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
                }
            }

            if (0 === stripos($data, 'Location:')) {
                $location = trim(substr($data, 9, -2));
            }

            return \strlen($data);
        }

        // End of headers: handle redirects and add to the activity list
        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $info['redirect_url'] = null;

        if (300 <= $statusCode && $statusCode < 400 && null !== $location) {
            $info['redirect_url'] = $resolveRedirect($ch, $location);
            $url = parse_url($location ?? ':');

            if (isset($url['host']) && null !== $ip = $multi->dnsCache[0][$url['host'] = strtolower($url['host'])] ?? null) {
                // Populate DNS cache for redirects if needed
                $port = $url['port'] ?? ('http' === ($url['scheme'] ?? parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), PHP_URL_SCHEME)) ? 80 : 443);
                curl_setopt($ch, CURLOPT_RESOLVE, ["{$url['host']}:$port:$ip"]);
                $multi->dnsCache[1]["-{$url['host']}:$port"] = "-{$url['host']}:$port";
            }
        }

        $location = null;

        if ($statusCode < 300 || 400 <= $statusCode || curl_getinfo($ch, CURLINFO_REDIRECT_COUNT) === $options['max_redirects']) {
            // Headers and redirects completed, time to get the response's body
            $multi->handlesActivity[$id] = [new FirstChunk()];

            if ('destruct' === $waitFor) {
                return 0;
            }

            if ($certinfo = curl_getinfo($ch, CURLINFO_CERTINFO)) {
                $info['peer_certificate_chain'] = array_map('openssl_x509_read', array_column($certinfo, 'Cert'));
            }

            curl_setopt($ch, CURLOPT_PRIVATE, 'content');
        }

        return \strlen($data);
    }
}
