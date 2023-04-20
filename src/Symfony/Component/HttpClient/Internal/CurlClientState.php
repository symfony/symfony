<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Response\CurlResponse;

/**
 * Internal representation of the cURL client's state.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class CurlClientState extends ClientState
{
    public ?\CurlMultiHandle $handle;
    public ?\CurlShareHandle $share;
    public bool $performing = false;

    /** @var PushedResponse[] */
    public array $pushedResponses = [];
    public DnsCache $dnsCache;
    /** @var float[] */
    public array $pauseExpiries = [];
    public int $execCounter = \PHP_INT_MIN;
    public ?LoggerInterface $logger = null;

    public static array $curlVersion;

    public function __construct(int $maxHostConnections, int $maxPendingPushes)
    {
        self::$curlVersion ??= curl_version();

        $this->handle = curl_multi_init();
        $this->dnsCache = new DnsCache();
        $this->reset();

        // Don't enable HTTP/1.1 pipelining: it forces responses to be sent in order
        if (\defined('CURLPIPE_MULTIPLEX')) {
            curl_multi_setopt($this->handle, \CURLMOPT_PIPELINING, \CURLPIPE_MULTIPLEX);
        }
        if (\defined('CURLMOPT_MAX_HOST_CONNECTIONS')) {
            $maxHostConnections = curl_multi_setopt($this->handle, \CURLMOPT_MAX_HOST_CONNECTIONS, 0 < $maxHostConnections ? $maxHostConnections : \PHP_INT_MAX) ? 0 : $maxHostConnections;
        }
        if (\defined('CURLMOPT_MAXCONNECTS') && 0 < $maxHostConnections) {
            curl_multi_setopt($this->handle, \CURLMOPT_MAXCONNECTS, $maxHostConnections);
        }

        // Skip configuring HTTP/2 push when it's unsupported or buggy, see https://bugs.php.net/77535
        if (0 >= $maxPendingPushes) {
            return;
        }

        // HTTP/2 push crashes before curl 7.61
        if (!\defined('CURLMOPT_PUSHFUNCTION') || 0x073D00 > self::$curlVersion['version_number'] || !(\CURL_VERSION_HTTP2 & self::$curlVersion['features'])) {
            return;
        }

        // Clone to prevent a circular reference
        $multi = clone $this;
        $multi->handle = null;
        $multi->share = null;
        $multi->pushedResponses = &$this->pushedResponses;
        $multi->logger = &$this->logger;
        $multi->handlesActivity = &$this->handlesActivity;
        $multi->openHandles = &$this->openHandles;

        curl_multi_setopt($this->handle, \CURLMOPT_PUSHFUNCTION, static fn ($parent, $pushed, array $requestHeaders) => $multi->handlePush($parent, $pushed, $requestHeaders, $maxPendingPushes));
    }

    public function reset(): void
    {
        foreach ($this->pushedResponses as $url => $response) {
            $this->logger?->debug(sprintf('Unused pushed response: "%s"', $url));
            curl_multi_remove_handle($this->handle, $response->handle);
            curl_close($response->handle);
        }

        $this->pushedResponses = [];
        $this->dnsCache->evictions = $this->dnsCache->evictions ?: $this->dnsCache->removals;
        $this->dnsCache->removals = $this->dnsCache->hostnames = [];

        $this->share = curl_share_init();

        curl_share_setopt($this->share, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_DNS);
        curl_share_setopt($this->share, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_SSL_SESSION);

        if (\defined('CURL_LOCK_DATA_CONNECT') && \PHP_VERSION_ID >= 80000) {
            curl_share_setopt($this->share, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_CONNECT);
        }
    }

    private function handlePush($parent, $pushed, array $requestHeaders, int $maxPendingPushes): int
    {
        $headers = [];
        $origin = curl_getinfo($parent, \CURLINFO_EFFECTIVE_URL);

        foreach ($requestHeaders as $h) {
            if (false !== $i = strpos($h, ':', 1)) {
                $headers[substr($h, 0, $i)][] = substr($h, 1 + $i);
            }
        }

        if (!isset($headers[':method']) || !isset($headers[':scheme']) || !isset($headers[':authority']) || !isset($headers[':path'])) {
            $this->logger?->debug(sprintf('Rejecting pushed response from "%s": pushed headers are invalid', $origin));

            return \CURL_PUSH_DENY;
        }

        $url = $headers[':scheme'][0].'://'.$headers[':authority'][0];

        // curl before 7.65 doesn't validate the pushed ":authority" header,
        // but this is a MUST in the HTTP/2 RFC; let's restrict pushes to the original host,
        // ignoring domains mentioned as alt-name in the certificate for now (same as curl).
        if (!str_starts_with($origin, $url.'/')) {
            $this->logger?->debug(sprintf('Rejecting pushed response from "%s": server is not authoritative for "%s"', $origin, $url));

            return \CURL_PUSH_DENY;
        }

        if ($maxPendingPushes <= \count($this->pushedResponses)) {
            $fifoUrl = key($this->pushedResponses);
            unset($this->pushedResponses[$fifoUrl]);
            $this->logger?->debug(sprintf('Evicting oldest pushed response: "%s"', $fifoUrl));
        }

        $url .= $headers[':path'][0];
        $this->logger?->debug(sprintf('Queueing pushed response: "%s"', $url));

        $this->pushedResponses[$url] = new PushedResponse(new CurlResponse($this, $pushed), $headers, $this->openHandles[(int) $parent][1] ?? [], $pushed);

        return \CURL_PUSH_OK;
    }
}
