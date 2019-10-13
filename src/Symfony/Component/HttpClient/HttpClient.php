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
            if ('\\' !== \DIRECTORY_SEPARATOR || ini_get('curl.cainfo') || ini_get('openssl.cafile') || ini_get('openssl.capath')) {
                return new CurlHttpClient($defaultOptions, $maxHostConnections, $maxPendingPushes);
            }

            @trigger_error('Configure the "curl.cainfo", "openssl.cafile" or "openssl.capath" php.ini setting to enable the CurlHttpClient', E_USER_WARNING);
        }

        return new NativeHttpClient($defaultOptions, $maxHostConnections);
    }

    /**
     * Creates a client that adds options (e.g. authentication headers) only when the request URL matches the provided base URI.
     */
    public static function createForBaseUri(string $baseUri, array $defaultOptions = [], int $maxHostConnections = 6, int $maxPendingPushes = 50): HttpClientInterface
    {
        $client = self::create([], $maxHostConnections, $maxPendingPushes);

        return ScopingHttpClient::forBaseUri($client, $baseUri, $defaultOptions);
    }
}
