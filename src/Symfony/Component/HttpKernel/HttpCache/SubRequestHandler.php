<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class SubRequestHandler
{
    /**
     * @return Response
     */
    public static function handle(HttpKernelInterface $kernel, Request $request, $type, $catch)
    {
        // save global state related to trusted headers and proxies
        $trustedProxies = Request::getTrustedProxies();
        $trustedHeaderSet = Request::getTrustedHeaderSet();
        $trustedHeaders = array(
            Request::HEADER_FORWARDED => Request::getTrustedHeaderName(Request::HEADER_FORWARDED, false),
            Request::HEADER_X_FORWARDED_FOR => Request::getTrustedHeaderName(Request::HEADER_X_FORWARDED_FOR, false),
            Request::HEADER_X_FORWARDED_HOST => Request::getTrustedHeaderName(Request::HEADER_X_FORWARDED_HOST, false),
            Request::HEADER_X_FORWARDED_PROTO => Request::getTrustedHeaderName(Request::HEADER_X_FORWARDED_PROTO, false),
            Request::HEADER_X_FORWARDED_PORT => Request::getTrustedHeaderName(Request::HEADER_X_FORWARDED_PORT, false),
        );

        // remove untrusted values
        $remoteAddr = $request->server->get('REMOTE_ADDR');
        if (!IpUtils::checkIp($remoteAddr, $trustedProxies)) {
            foreach (array_filter($trustedHeaders) as $name) {
                $request->headers->remove($name);
            }
        }

        // compute trusted values, taking any trusted proxies into account
        $trustedIps = array();
        $trustedValues = array();
        foreach (array_reverse($request->getClientIps()) as $ip) {
            $trustedIps[] = $ip;
            $trustedValues[] = sprintf('for="%s"', $ip);
        }
        if ($ip !== $remoteAddr) {
            $trustedIps[] = $remoteAddr;
            $trustedValues[] = sprintf('for="%s"', $remoteAddr);
        }

        // set trusted values, reusing as much as possible the global trusted settings
        if ($name = $trustedHeaders[Request::HEADER_FORWARDED]) {
            $trustedValues[0] .= sprintf(';host="%s";proto=%s', $request->getHttpHost(), $request->getScheme());
            $request->headers->set($name, implode(', ', $trustedValues));
        }
        if ($name = $trustedHeaders[Request::HEADER_X_FORWARDED_FOR]) {
            $request->headers->set($name, implode(', ', $trustedIps));
        }
        if (!$name && !$trustedHeaders[Request::HEADER_FORWARDED]) {
            Request::setTrustedProxies($trustedProxies, $trustedHeaderSet | Request::HEADER_X_FORWARDED_FOR);
            $request->headers->set(Request::getTrustedHeaderName(Request::HEADER_X_FORWARDED_FOR, false), implode(', ', $trustedIps));
        }

        // fix the client IP address by setting it to 127.0.0.1,
        // which is the core responsibility of this method
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        // ensure 127.0.0.1 is set as trusted proxy
        if (!IpUtils::checkIp('127.0.0.1', $trustedProxies)) {
            Request::setTrustedProxies(array_merge($trustedProxies, array('127.0.0.1')), Request::getTrustedHeaderSet());
        }

        try {
            return $kernel->handle($request, $type, $catch);
        } finally {
            // restore global state
            Request::setTrustedProxies($trustedProxies, $trustedHeaderSet);
        }
    }
}
