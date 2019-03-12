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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
     *
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public static function create(array $defaultOptions = [], LoggerInterface $logger = null, int $maxHostConnections = 6): HttpClientInterface
    {
        if (null === $logger) {
            $logger = new NullLogger();
        }

        if (\extension_loaded('curl')) {
            $logger->debug('Curl extension is enabled. Creating client.', ['client' => CurlHttpClient::class]);

            return new CurlHttpClient($defaultOptions, $logger, $maxHostConnections);
        }

        $logger->debug('Curl extension is disabled. Creating client.', ['client' => NativeHttpClient::class]);

        return new NativeHttpClient($defaultOptions, $logger, $maxHostConnections);
    }
}
