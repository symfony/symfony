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
    /** @var \CurlMultiHandle|resource */
    public $handle;
    /** @var PushedResponse[] */
    public $pushedResponses = [];
    /** @var DnsCache */
    public $dnsCache;
    /** @var float[] */
    public $pauseExpiries = [];
    public $execCounter = \PHP_INT_MIN;
    /** @var LoggerInterface|null */
    public $logger;

    public static $curlVersion;

    private $maxHostConnections;
    private $maxPendingPushes;

    public function __construct(int $maxHostConnections, int $maxPendingPushes)
    {
        self::$curlVersion = self::$curlVersion ?? curl_version();

        $this->handle = curl_multi_init();
        $this->dnsCache = new DnsCache();
        $this->maxHostConnections = $maxHostConnections;
        $this->maxPendingPushes = $maxPendingPushes;

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
        if (0 >= $maxPendingPushes || \PHP_VERSION_ID < 70217 || (\PHP_VERSION_ID >= 70300 && \PHP_VERSION_ID < 70304)) {
            return;
        }

        // HTTP/2 push crashes before curl 7.61
        if (!\defined('CURLMOPT_PUSHFUNCTION') || 0x073D00 > self::$curlVersion['version_number'] || !(\CURL_VERSION_HTTP2 & self::$curlVersion['features'])) {
            return;
        }

        curl_multi_setopt($this->handle, \CURLMOPT_PUSHFUNCTION, function ($parent, $pushed, array $requestHeaders) use ($maxPendingPushes) {
            return $this->handlePush($parent, $pushed, $requestHeaders, $maxPendingPushes);
        });
    }

    public function reset()
    {
        if ($this->logger) {
            foreach ($this->pushedResponses as $url => $response) {
                $this->logger->debug(sprintf('Unused pushed response: "%s"', $url));
            }
        }

        $this->pushedResponses = [];
        $this->dnsCache->evictions = $this->dnsCache->evictions ?: $this->dnsCache->removals;
        $this->dnsCache->removals = $this->dnsCache->hostnames = [];

        if (\is_resource($this->handle) || $this->handle instanceof \CurlMultiHandle) {
            if (\defined('CURLMOPT_PUSHFUNCTION')) {
                curl_multi_setopt($this->handle, \CURLMOPT_PUSHFUNCTION, null);
            }

            $this->__construct($this->maxHostConnections, $this->maxPendingPushes);
        }
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        foreach ($this->openHandles as [$ch]) {
            if (\is_resource($ch) || $ch instanceof \CurlHandle) {
                curl_setopt($ch, \CURLOPT_VERBOSE, false);
            }
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
            $this->logger && $this->logger->debug(sprintf('Rejecting pushed response from "%s": pushed headers are invalid', $origin));

            return \CURL_PUSH_DENY;
        }

        $url = $headers[':scheme'][0].'://'.$headers[':authority'][0];

        // curl before 7.65 doesn't validate the pushed ":authority" header,
        // but this is a MUST in the HTTP/2 RFC; let's restrict pushes to the original host,
        // ignoring domains mentioned as alt-name in the certificate for now (same as curl).
        if (!str_starts_with($origin, $url.'/')) {
            $this->logger && $this->logger->debug(sprintf('Rejecting pushed response from "%s": server is not authoritative for "%s"', $origin, $url));

            return \CURL_PUSH_DENY;
        }

        if ($maxPendingPushes <= \count($this->pushedResponses)) {
            $fifoUrl = key($this->pushedResponses);
            unset($this->pushedResponses[$fifoUrl]);
            $this->logger && $this->logger->debug(sprintf('Evicting oldest pushed response: "%s"', $fifoUrl));
        }

        $url .= $headers[':path'][0];
        $this->logger && $this->logger->debug(sprintf('Queueing pushed response: "%s"', $url));

        $this->pushedResponses[$url] = new PushedResponse(new CurlResponse($this, $pushed), $headers, $this->openHandles[(int) $parent][1] ?? [], $pushed);

        return \CURL_PUSH_OK;
    }
}
