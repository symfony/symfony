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

use Psl\DNS\ResolverInterface;
use Psl\HTTP\Client as PslClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\PslClientState;
use Symfony\Component\HttpClient\Response\PslResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

if (!interface_exists(PslClient\ClientInterface::class) || !interface_exists(ResolverInterface::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\PslHttpClient" as the "php-standard-library/http-client" or "php-standard-library/dns" package(s) are not installed. Try running "composer require php-standard-library/http-client php-standard-library/dns".');
}

/**
 * An HTTP client implementation based on the PHP Standard Library (PSL).
 *
 * @author Seifeddine Gmati <azjezz@carthage.software>
 */
final class PslHttpClient implements HttpClientInterface, LoggerAwareInterface, ResetInterface
{
    use HttpClientTrait;
    use LoggerAwareTrait;

    private array $defaultOptions = HttpClientInterface::OPTIONS_DEFAULTS;
    private static array $emptyDefaults = HttpClientInterface::OPTIONS_DEFAULTS;
    private PslClientState $multi;

    /**
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public function __construct(array $defaultOptions = [])
    {
        $this->defaultOptions['buffer'] ??= self::shouldBuffer(...);

        if ($defaultOptions) {
            [, $this->defaultOptions] = self::prepareRequest(null, null, $defaultOptions, $this->defaultOptions);
        }

        $this->multi = new PslClientState($this->logger);
    }

    /**
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        [$url, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions);

        $options['proxy'] = self::getProxy($options['proxy'], $url, $options['no_proxy']);

        if ($options['bindto']) {
            if (str_starts_with($options['bindto'], 'if!')) {
                throw new TransportException(__CLASS__.' cannot bind to network interfaces, use e.g. CurlHttpClient instead.');
            }
            if (str_starts_with($options['bindto'], 'host!')) {
                $options['bindto'] = substr($options['bindto'], 5);
            }
        }

        if (('' !== $options['body'] || 'POST' === $method || isset($options['normalized_headers']['content-length'])) && !isset($options['normalized_headers']['content-type'])) {
            $options['headers'][] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if (!isset($options['normalized_headers']['user-agent'])) {
            $options['headers'][] = 'User-Agent: Symfony HttpClient (PSL)';
        }

        if (0 < $options['max_duration']) {
            $options['timeout'] = min($options['max_duration'], $options['timeout']);
        }

        if ($options['resolve']) {
            $this->multi->dnsCache = $options['resolve'] + $this->multi->dnsCache;
        }

        if ($options['peer_fingerprint'] && !isset($options['peer_fingerprint']['pin-sha256'])) {
            throw new TransportException(__CLASS__.' supports only "pin-sha256" fingerprints.');
        }

        $options['url'] = implode('', $url);

        if (str_contains($url['authority'] ?? '', '@') && !isset($options['normalized_headers']['authorization'])) {
            $parsed = parse_url($options['url']);
            if (isset($parsed['user'])) {
                $auth = rawurldecode($parsed['user']);
                if (isset($parsed['pass'])) {
                    $auth .= ':'.rawurldecode($parsed['pass']);
                }
                $options['headers'][] = 'Authorization: Basic '.base64_encode($auth);
            }
        }
        $options['http_method'] = $method;

        if ($options['http_version']) {
            // Normalize for PSL
            $options['http_version'] = match ((float) $options['http_version']) {
                1.0 => '1.0',
                1.1 => '1.1',
                default => '2',
            };
        }

        return new PslResponse($this->multi, $options, $this->logger);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof PslResponse) {
            $responses = [$responses];
        }

        return new ResponseStream(PslResponse::stream($responses, $timeout));
    }

    public function reset(): void
    {
        $this->multi->dnsCache = [];
    }
}
