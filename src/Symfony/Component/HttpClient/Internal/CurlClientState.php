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

    public DnsCache $dnsCache;
    /** @var float[] */
    public array $pauseExpiries = [];
    public int $execCounter = \PHP_INT_MIN;
    public ?LoggerInterface $logger = null;

    public static array $curlVersion;

    public function __construct(int $maxHostConnections)
    {
        self::$curlVersion ??= curl_version();

        $this->handle = curl_multi_init();
        $this->dnsCache = new DnsCache();
        $this->reset();

        // Don't enable HTTP/1.1 pipelining: it forces responses to be sent in order
        if (\defined('CURLPIPE_MULTIPLEX')) {
            curl_multi_setopt($this->handle, \CURLMOPT_PIPELINING, \CURLPIPE_MULTIPLEX);
        }
        if (\defined('CURLMOPT_MAX_HOST_CONNECTIONS') && 0 < $maxHostConnections) {
            $maxHostConnections = curl_multi_setopt($this->handle, \CURLMOPT_MAX_HOST_CONNECTIONS, $maxHostConnections) ? min(50 * $maxHostConnections, 4294967295) : $maxHostConnections;
        }
        if (\defined('CURLMOPT_MAXCONNECTS') && 0 < $maxHostConnections) {
            curl_multi_setopt($this->handle, \CURLMOPT_MAXCONNECTS, $maxHostConnections);
        }
    }

    public function reset(): void
    {
        $this->dnsCache->evictions = $this->dnsCache->evictions ?: $this->dnsCache->removals;
        $this->dnsCache->removals = $this->dnsCache->hostnames = [];

        $this->share = curl_share_init();

        curl_share_setopt($this->share, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_DNS);
        curl_share_setopt($this->share, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_SSL_SESSION);

        if (\defined('CURL_LOCK_DATA_CONNECT')) {
            curl_share_setopt($this->share, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_CONNECT);
        }
    }
}
