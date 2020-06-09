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

/**
 * Internal representation of the cURL client's state.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class CurlClientState extends ClientState
{
    /** @var resource */
    public $handle;
    /** @var PushedResponse[] */
    public $pushedResponses = [];
    /** @var DnsCache */
    public $dnsCache;
    /** @var float[] */
    public $pauseExpiries = [];
    public $execCounter = PHP_INT_MIN;

    public function __construct()
    {
        $this->handle = curl_multi_init();
        $this->dnsCache = new DnsCache();
    }
}
