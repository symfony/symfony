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

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decorator that blocks requests to private networks by default.
 *
 * @author Hallison Boaventura <hallisonboaventura@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class NoPrivateNetworkHttpClient implements HttpClientInterface, ResetInterface
{
    use AsyncDecoratorTrait;
    use HttpClientTrait;

    private array $defaultOptions = self::OPTIONS_DEFAULTS;
    private HttpClientInterface $client;
    private ?array $subnets;
    private int $ipFlags;
    private \ArrayObject $dnsCache;

    /**
     * @param string|array|null $subnets String or array of subnets using CIDR notation that should be considered private.
     *                                   If null is passed, the standard private subnets will be used.
     */
    public function __construct(HttpClientInterface $client, string|array|null $subnets = null)
    {
        if (!class_exists(IpUtils::class)) {
            throw new \LogicException(\sprintf('You cannot use "%s" if the HttpFoundation component is not installed. Try running "composer require symfony/http-foundation".', __CLASS__));
        }

        if (null === $subnets) {
            $ipFlags = \FILTER_FLAG_IPV4 | \FILTER_FLAG_IPV6;
        } else {
            $ipFlags = 0;
            foreach ((array) $subnets as $subnet) {
                $ipFlags |= str_contains($subnet, ':') ? \FILTER_FLAG_IPV6 : \FILTER_FLAG_IPV4;
            }
        }

        if (!\defined('STREAM_PF_INET6')) {
            $ipFlags &= ~\FILTER_FLAG_IPV6;
        }

        $this->client = $client;
        $this->subnets = null !== $subnets ? (array) $subnets : null;
        $this->ipFlags = $ipFlags;
        $this->dnsCache = new \ArrayObject();
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        [$url, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions, true);

        $redirectHeaders = parse_url($url['authority']);
        $host = $redirectHeaders['host'];
        $url = implode('', $url);
        $dnsCache = $this->dnsCache;

        $ip = self::dnsResolve($dnsCache, $host, $this->ipFlags, $options);
        self::ipCheck($ip, $this->subnets, $this->ipFlags, $host, $url);

        $onProgress = $options['on_progress'] ?? null;
        $subnets = $this->subnets;
        $ipFlags = $this->ipFlags;

        $options['on_progress'] = static function (int $dlNow, int $dlSize, array $info) use ($onProgress, $subnets, $ipFlags): void {
            static $lastPrimaryIp = '';

            if (!\in_array($info['primary_ip'] ?? '', ['', $lastPrimaryIp], true)) {
                self::ipCheck($info['primary_ip'], $subnets, $ipFlags, null, $info['url']);
                $lastPrimaryIp = $info['primary_ip'];
            }

            null !== $onProgress && $onProgress($dlNow, $dlSize, $info);
        };

        if (0 >= $maxRedirects = $options['max_redirects']) {
            return new AsyncResponse($this->client, $method, $url, $options);
        }

        $options['max_redirects'] = 0;
        $redirectHeaders['with_auth'] = $redirectHeaders['no_auth'] = $options['headers'];

        if (isset($options['normalized_headers']['host']) || isset($options['normalized_headers']['authorization']) || isset($options['normalized_headers']['cookie'])) {
            $redirectHeaders['no_auth'] = array_filter($redirectHeaders['no_auth'], static function ($h) {
                return 0 !== stripos($h, 'Host:') && 0 !== stripos($h, 'Authorization:') && 0 !== stripos($h, 'Cookie:');
            });
        }

        return new AsyncResponse($this->client, $method, $url, $options, static function (ChunkInterface $chunk, AsyncContext $context) use (&$method, &$options, $maxRedirects, &$redirectHeaders, $subnets, $ipFlags, $dnsCache): \Generator {
            if (null !== $chunk->getError() || $chunk->isTimeout() || !$chunk->isFirst()) {
                yield $chunk;

                return;
            }

            $statusCode = $context->getStatusCode();

            if ($statusCode < 300 || 400 <= $statusCode || null === $url = $context->getInfo('redirect_url')) {
                $context->passthru();

                yield $chunk;

                return;
            }

            $host = parse_url($url, \PHP_URL_HOST);
            $ip = self::dnsResolve($dnsCache, $host, $ipFlags, $options);
            self::ipCheck($ip, $subnets, $ipFlags, $host, $url);

            // Do like curl and browsers: turn POST to GET on 301, 302 and 303
            if (303 === $statusCode || 'POST' === $method && \in_array($statusCode, [301, 302], true)) {
                $method = 'HEAD' === $method ? 'HEAD' : 'GET';
                unset($options['body'], $options['json']);

                if (isset($options['normalized_headers']['content-length']) || isset($options['normalized_headers']['content-type']) || isset($options['normalized_headers']['transfer-encoding'])) {
                    $filterContentHeaders = static function ($h) {
                        return 0 !== stripos($h, 'Content-Length:') && 0 !== stripos($h, 'Content-Type:') && 0 !== stripos($h, 'Transfer-Encoding:');
                    };
                    $options['headers'] = array_filter($options['headers'], $filterContentHeaders);
                    $redirectHeaders['no_auth'] = array_filter($redirectHeaders['no_auth'], $filterContentHeaders);
                    $redirectHeaders['with_auth'] = array_filter($redirectHeaders['with_auth'], $filterContentHeaders);
                }
            }

            // Authorization and Cookie headers MUST NOT follow except for the initial host name
            $port = parse_url($url, \PHP_URL_PORT);
            $options['headers'] = $redirectHeaders['host'] === $host && ($redirectHeaders['port'] ?? null) === $port ? $redirectHeaders['with_auth'] : $redirectHeaders['no_auth'];

            static $redirectCount = 0;
            $context->setInfo('redirect_count', ++$redirectCount);

            $context->replaceRequest($method, $url, $options);

            if ($redirectCount >= $maxRedirects) {
                $context->passthru();
            }
        });
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);
        $clone->defaultOptions = self::mergeDefaultOptions($options, $this->defaultOptions);

        return $clone;
    }

    public function reset(): void
    {
        $this->dnsCache->exchangeArray([]);

        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }

    private static function dnsResolve(\ArrayObject $dnsCache, string $host, int $ipFlags, array &$options): string
    {
        if ($ip = filter_var(trim($host, '[]'), \FILTER_VALIDATE_IP) ?: $options['resolve'][$host] ?? false) {
            return $ip;
        }

        if ($dnsCache->offsetExists($host)) {
            return $dnsCache[$host];
        }

        if ((\FILTER_FLAG_IPV4 & $ipFlags) && $ip = gethostbynamel($host)) {
            return $options['resolve'][$host] = $dnsCache[$host] = $ip[0];
        }

        if (!(\FILTER_FLAG_IPV6 & $ipFlags)) {
            return $host;
        }

        if ($ip = dns_get_record($host, \DNS_AAAA)) {
            $ip = $ip[0]['ipv6'];
        } elseif (\extension_loaded('sockets')) {
            if (!$info = socket_addrinfo_lookup($host, 0, ['ai_socktype' => \SOCK_STREAM, 'ai_family' => \AF_INET6])) {
                return $host;
            }

            $ip = socket_addrinfo_explain($info[0])['ai_addr']['sin6_addr'];
        } elseif ('localhost' === $host || 'localhost.' === $host) {
            $ip = '::1';
        } else {
            return $host;
        }

        return $options['resolve'][$host] = $dnsCache[$host] = $ip;
    }

    private static function ipCheck(string $ip, ?array $subnets, int $ipFlags, ?string $host, string $url): void
    {
        if (null === $subnets) {
            // Quick check, but not reliable enough, see https://github.com/php/php-src/issues/16944
            $ipFlags |= \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE;
        }

        if (false !== filter_var($ip, \FILTER_VALIDATE_IP, $ipFlags) && !IpUtils::checkIp($ip, $subnets ?? IpUtils::PRIVATE_SUBNETS)) {
            return;
        }

        if (null !== $host) {
            $type = 'Host';
        } else {
            $host = $ip;
            $type = 'IP';
        }

        throw new TransportException($type.\sprintf(' "%s" is blocked for "%s".', $host, $url));
    }
}
