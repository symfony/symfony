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

class IpRetriever implements IpRetrieverInterface
{
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
            null !== $request->getTrustedHeaderName($request::HEADER_FORWARDED)
            && $request->headers->has($request->getTrustedHeaderName($request::HEADER_FORWARDED))
        ) {
            $forwardedHeader = $request->headers->get($request->getTrustedHeaderName($request::HEADER_FORWARDED));
            preg_match_all('{(for)=("?\[?)([a-z0-9\.:_\-/]*)}', $forwardedHeader, $matches);
            $clientIps = $matches[3];
        } elseif (
            null !== $request->getTrustedHeaderName($request::HEADER_CLIENT_IP)
            && $request->headers->has($request->getTrustedHeaderName($request::HEADER_CLIENT_IP))
        ) {
            $clientIps = array_map(
                'trim',
                explode(
                    ',',
                    $request->headers->get($request->getTrustedHeaderName($request::HEADER_CLIENT_IP))
                )
            );
        }

        $clientIps[] = $ip; // Complete the IP chain with the IP the request actually came from
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
