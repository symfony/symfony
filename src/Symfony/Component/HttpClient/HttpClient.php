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

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A factory to instantiate the best possible HTTP client for the runtime.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.3
 */
final class HttpClient
{
    /**
     * @param array $defaultOptions     Default requests' options
     * @param int   $maxHostConnections The maximum number of connections to a single host
     * @param int   $maxPendingPushes   The maximum number of pushed responses to accept in the queue
     *
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public static function create(array $defaultOptions = [], int $maxHostConnections = 6, int $maxPendingPushes = 50): HttpClientInterface
    {
        if (\extension_loaded('curl')) {
            return new CurlHttpClient($defaultOptions, $maxHostConnections, $maxPendingPushes);
        }

        return new NativeHttpClient($defaultOptions, $maxHostConnections);
    }
}
