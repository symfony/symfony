<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\IpRetriever;


use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * IpRetriever
 *
 * Can retrieve real user ip from various contexts (proxified query or not).
 * If you use a reverse proxy, set his ip with setTrustedProxies
 * otherwise, you'll get your reverse proxy ip anyway.
 *
 * @author Xavier Leune <xavier.leune@gmail.com>
 */
class IpRetriever implements IpRetrieverInterface
{
    /**
     * Names for headers that can be trusted when
     * using trusted proxies.
     *
     * The FORWARDED header is the standard as of rfc7239.
     *
     * The other headers are non-standard, but widely used
     * by popular reverse proxies (like Apache mod_proxy or Amazon EC2).
     */
    protected $trustedHeaders = array(
        self::HEADER_FORWARDED => 'FORWARDED',
        self::HEADER_CLIENT_IP => 'X_FORWARDED_FOR',
        self::HEADER_REAL_IP => 'X_REAL_IP',
    );

    protected $trustedProxies = array();

    /**
     * {@inheritdoc}
     */
    public function setTrustedProxies(array $proxies)
    {
        $this->trustedProxies = $proxies;
    }

    /**
     * {@inheritdoc}
     */
    public function getTrustedProxies()
    {
        return $this->getTrustedProxies();
    }

    /**
     * {@inheritdoc}
     */
    public function setTrustedHeaderName($key, $value)
    {
        if (!array_key_exists($key, $this->trustedHeaders)) {
            throw new \InvalidArgumentException(sprintf('Unable to set the trusted header name for key "%s".', $key));
        }

        $this->trustedHeaders[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getTrustedHeaderName($key)
    {
        if (!array_key_exists($key, $this->trustedHeaders)) {
            throw new \InvalidArgumentException(sprintf('Unable to get the trusted header name for key "%s".', $key));
        }

        return $this->trustedHeaders[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function getClientIp(Request $request)
    {
        $ipAddresses = $this->getClientIps($request);

        return $ipAddresses[0];
    }

    /**
     * {@inheritdoc}
     */
    public function getClientIps(Request $request)
    {
        $clientIps = array();
        $ip = $request->server->get('REMOTE_ADDR');

        if ($this->trustedProxies === array()) {
            return array($ip);
        }

        if (
            null !== $this->getTrustedHeaderName(self::HEADER_FORWARDED)
            && $request->headers->has($this->getTrustedHeaderName(self::HEADER_FORWARDED))
        ) {
            $forwardedHeader = $request->headers->get($this->getTrustedHeaderName(self::HEADER_FORWARDED));
            preg_match_all('{(for)=("?\[?)([a-z0-9\.:_\-/]*)}', $forwardedHeader, $matches);
            $clientIps = $matches[3];
        } elseif (
            null !== $this->getTrustedHeaderName(self::HEADER_CLIENT_IP)
            && $request->headers->has($this->getTrustedHeaderName(self::HEADER_CLIENT_IP))
        ) {
            $clientIps = array_map(
                'trim',
                explode(
                    ',',
                    $request->headers->get($this->getTrustedHeaderName(self::HEADER_CLIENT_IP))
                )
            );
        }

        if (
            null !==  $this->getTrustedHeaderName(self::HEADER_REAL_IP)
            && $request->headers->has($this->getTrustedHeaderName(self::HEADER_REAL_IP))
        ) {
            $clientIps = array_merge(
                $clientIps,
                array_map(
                    'trim',
                    explode(
                        ',',
                        $request->headers->get($this->getTrustedHeaderName(self::HEADER_REAL_IP))
                    )
                )
            );
        }

        $clientIps[] = $ip; // Complete the IP chain with the IP the request actually came from

        return $this->filterIps($clientIps);
    }

    protected function filterIps(array $clientIps)
    {
        $ip = $clientIps[0]; // Fallback to this when the client IP falls into the range of trusted proxies
        foreach ($clientIps as $key => $clientIp) {
            // Remove port (unfortunately, it does happen)
            if (preg_match('{((?:\d+\.){3}\d+)\:\d+}', $clientIp, $match)) {
                $clientIps[$key] = $clientIp = $match[1];
            }

            if (IpUtils::checkIp($clientIp, $this->trustedProxies)) {
                unset($clientIps[$key]);
            }
        }

        // Now the IP chain contains only untrusted proxies and the client IP
        return $clientIps ? array_reverse($clientIps) : array($ip);
    }
}
