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

    public function __construct()
    {
        $this->handle = curl_multi_init();
        $this->dnsCache = new DnsCache();
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

            $active = 0;
            while (\CURLM_CALL_MULTI_PERFORM === curl_multi_exec($this->handle, $active));
        }

        foreach ($this->openHandles as [$ch]) {
            if (\is_resource($ch) || $ch instanceof \CurlHandle) {
                curl_setopt($ch, \CURLOPT_VERBOSE, false);
            }
        }

        curl_multi_close($this->handle);
        $this->handle = curl_multi_init();
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $this->reset();
    }
}
