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

use Amp\Http\Client\Request as AmpRequest;
use Amp\Http\HttpMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A factory to instantiate the best possible HTTP client for the runtime.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class HttpClient
{
    /**
     * @param array $defaultOptions     Default request's options
     * @param int   $maxHostConnections The maximum number of connections to a single host
     *
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public static function create(array $defaultOptions = [], int $maxHostConnections = 6): HttpClientInterface
    {
        if (\extension_loaded('curl')) {
            if ('\\' !== \DIRECTORY_SEPARATOR || isset($defaultOptions['cafile']) || isset($defaultOptions['capath']) || \ini_get('curl.cainfo') || \ini_get('openssl.cafile') || \ini_get('openssl.capath')) {
                return new CurlHttpClient($defaultOptions, $maxHostConnections);
            }

            @trigger_error('Configure the "curl.cainfo", "openssl.cafile" or "openssl.capath" php.ini setting to enable the CurlHttpClient', \E_USER_WARNING);
        }

        if (class_exists(AmpRequest::class) && (\PHP_VERSION_ID >= 80400 || !is_subclass_of(AmpRequest::class, HttpMessage::class))) {
            return new AmpHttpClient($defaultOptions, null, $maxHostConnections);
        }

        @trigger_error('Install the curl extension or run "composer require amphp/http-client:^5" to perform async HTTP operations, including HTTP/2 support', \E_USER_NOTICE);

        return new NativeHttpClient($defaultOptions, $maxHostConnections);
    }

    /**
     * Creates a client that adds options (e.g. authentication headers) only when the request URL matches the provided base URI.
     */
    public static function createForBaseUri(string $baseUri, array $defaultOptions = [], int $maxHostConnections = 6): HttpClientInterface
    {
        $client = self::create([], $maxHostConnections);

        return ScopingHttpClient::forBaseUri($client, $baseUri, $defaultOptions);
    }
}
