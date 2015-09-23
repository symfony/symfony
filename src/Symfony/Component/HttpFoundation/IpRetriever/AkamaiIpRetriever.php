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

use Symfony\Component\HttpFoundation\Request;

/**
 * AkamaiIpRetriever.
 *
 * Warning : this class is less strict than IpRetriever.
 * It has been created to handle akamai CDN.
 * Since your application doesn't know if the ip comes from akamai or not
 * you have to filter traffic at another network level to avoid ip spoofing.
 *
 * If you don't use akamai, use IpRetriever.
 *
 * @see IpRetriever
 *
 * @author Xavier Leune <xavier.leune@gmail.com>
 */
class AkamaiIpRetriever extends IpRetriever
{
    const HEADER_TRUE_CLIENT_IP = 'true_client_ip';
    const HEADER_AKAMAI_ORIGIN_HOP = 'akamai_origin_hop';

    /**
     * {@inheritdoc}
     */
    protected $trustedHeaders = array(
        self::HEADER_FORWARDED => 'FORWARDED',
        self::HEADER_CLIENT_IP => 'X_FORWARDED_FOR',
        self::HEADER_REAL_IP => 'X_REAL_IP',
        self::HEADER_AKAMAI_ORIGIN_HOP => 'AKAMAI_ORIGIN_HOP',
        self::HEADER_TRUE_CLIENT_IP => 'TRUE_CLIENT_IP',
    );

    /**
     * {@inheritdoc}
     */
    public function getClientIps(Request $request)
    {
        $clientIps = array();
        $ip = $request->server->get('REMOTE_ADDR');

        if (
            null !== $this->getTrustedHeaderName(self::HEADER_FORWARDED)
            && $request->headers->has($this->getTrustedHeaderName(self::HEADER_FORWARDED))
        ) {
            $forwardedHeader = $request->headers->get($this->getTrustedHeaderName(self::HEADER_FORWARDED));
            preg_match_all('{(for)=("?\[?)([a-z0-9\.:_\-/]*)}', $forwardedHeader, $matches);
            $clientIps = $matches[3];

            if (
                null !== $this->getTrustedHeaderName(self::HEADER_AKAMAI_ORIGIN_HOP)
                && $request->headers->has($this->getTrustedHeaderName(self::HEADER_AKAMAI_ORIGIN_HOP))
            ) {
                // We remove forwarded ips from akamai
                $forwardAkamai = $request->headers->get($this->getTrustedHeaderName(self::HEADER_AKAMAI_ORIGIN_HOP));
                $clientIps = array_slice(
                    $clientIps,
                    0,
                    count($clientIps) - (int) $forwardAkamai - 1
                );
            }
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

        // Akamai gives the real ip with HTTP_TRUE_CLIENT_IP
        if (
            null !==  $this->getTrustedHeaderName(self::HEADER_TRUE_CLIENT_IP)
            && $request->headers->has($this->getTrustedHeaderName(self::HEADER_TRUE_CLIENT_IP))
        ) {
            $clientIps = array_merge(
                $clientIps,
                array_map(
                    'trim',
                    explode(
                        ',',
                        $request->headers->get($this->getTrustedHeaderName(self::HEADER_TRUE_CLIENT_IP))
                    )
                )
            );
        }

        $clientIps[] = $ip; // Complete the IP chain with the IP the request actually came from

        return $this->filterIps($clientIps);
    }
}
