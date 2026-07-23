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
use Symfony\Component\HttpClient\Internal\FollowRedirectsTrait;
use Symfony\Component\HttpFoundation\IpUtils;
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
    use FollowRedirectsTrait;
    use HttpClientTrait;

    private array $defaultOptions = self::OPTIONS_DEFAULTS;
    private HttpClientInterface $client;
    private ?array $subnets;
    private array $allowList;
    private int $ipFlags;
    private \ArrayObject $dnsCache;

    /**
     * @param string|array|null $subnets   String or array of subnets using CIDR notation that should be considered private.
     *                                     If null is passed, the standard private subnets will be used.
     * @param string|array      $allowList String or array of IPs/subnets using CIDR notation that should be allowed
     *                                     even when they would otherwise match the private subnets. Useful e.g. to allow
     *                                     reaching a local proxy or a known internal host while still blocking the rest
     *                                     of the private network.
     */
    public function __construct(HttpClientInterface $client, string|array|null $subnets = null, string|array $allowList = [])
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

        foreach ((array) $allowList as $allowed) {
            $ipFlags |= str_contains($allowed, ':') ? \FILTER_FLAG_IPV6 : \FILTER_FLAG_IPV4;
        }

        if (!\defined('STREAM_PF_INET6')) {
            $ipFlags &= ~\FILTER_FLAG_IPV6;
        }

        $this->client = $client;
        $this->subnets = null !== $subnets ? (array) $subnets : null;
        $this->allowList = (array) $allowList;
        $this->ipFlags = $ipFlags;
        $this->dnsCache = new \ArrayObject();
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        [$url, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions, true);

        $host = parse_url($url['authority'], \PHP_URL_HOST);
        $url = implode('', $url);

        $dnsCache = $this->dnsCache;
        $subnets = $this->subnets;
        $allowList = $this->allowList;
        $ipFlags = $this->ipFlags;

        $checkHost = static function (string $host, string $url, array &$options) use ($dnsCache, $subnets, $allowList, $ipFlags): void {
            $ip = self::dnsResolve($dnsCache, $host, $ipFlags, $options);
            self::ipCheck($ip, $subnets, $allowList, $ipFlags, $host, $url);
        };

        $checkHost($host, $url, $options);

        $onProgress = $options['on_progress'] ?? null;
        $options['on_progress'] = static function (int $dlNow, int $dlSize, array $info) use ($onProgress, $subnets, $allowList, $ipFlags): void {
            static $lastPrimaryIp = '';

            if (!\in_array($info['primary_ip'] ?? '', ['', $lastPrimaryIp], true)) {
                self::ipCheck($info['primary_ip'], $subnets, $allowList, $ipFlags, null, $info['url']);
                $lastPrimaryIp = $info['primary_ip'];
            }

            null !== $onProgress && $onProgress($dlNow, $dlSize, $info);
        };

        return $this->followRedirects($method, $url, $host, $options, $checkHost);
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

    private static function ipCheck(string $ip, ?array $subnets, array $allowList, int $ipFlags, ?string $host, string $url): void
    {
        if (null === $subnets) {
            // Quick check, but not reliable enough, see https://github.com/php/php-src/issues/16944
            $ipFlags |= \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE;
        }

        if (false !== filter_var($ip, \FILTER_VALIDATE_IP, $ipFlags) && !IpUtils::checkIp($ip, $subnets ?? IpUtils::PRIVATE_SUBNETS)) {
            return;
        }

        if ($allowList && false !== filter_var($ip, \FILTER_VALIDATE_IP) && IpUtils::checkIp($ip, $allowList)) {
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
