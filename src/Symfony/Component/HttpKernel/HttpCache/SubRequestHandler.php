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
    public static function handle(HttpKernelInterface $kernel, Request $request, $type, $catch): Response
    {
        // save global state related to trusted headers and proxies
        $trustedProxies = Request::getTrustedProxies();
        $trustedHeaderSet = Request::getTrustedHeaderSet();

        // remove untrusted values
        $remoteAddr = $request->server->get('REMOTE_ADDR');
        if (!IpUtils::checkIp($remoteAddr, $trustedProxies)) {
            $trustedHeaders = array(
                'FORWARDED' => $trustedHeaderSet & Request::HEADER_FORWARDED,
                'X_FORWARDED_FOR' => $trustedHeaderSet & Request::HEADER_X_FORWARDED_FOR,
                'X_FORWARDED_HOST' => $trustedHeaderSet & Request::HEADER_X_FORWARDED_HOST,
                'X_FORWARDED_PROTO' => $trustedHeaderSet & Request::HEADER_X_FORWARDED_PROTO,
                'X_FORWARDED_PORT' => $trustedHeaderSet & Request::HEADER_X_FORWARDED_PORT,
            );
            foreach (array_filter($trustedHeaders) as $name => $key) {
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
        if (Request::HEADER_FORWARDED & $trustedHeaderSet) {
            $trustedValues[0] .= sprintf(';host="%s";proto=%s', $request->getHttpHost(), $request->getScheme());
            $request->headers->set('Forwarded', implode(', ', $trustedValues));
        }
        if (Request::HEADER_X_FORWARDED_FOR & $trustedHeaderSet) {
            $request->headers->set('X-Forwarded-For', implode(', ', $trustedIps));
        } elseif (!(Request::HEADER_FORWARDED & $trustedHeaderSet)) {
            Request::setTrustedProxies($trustedProxies, $trustedHeaderSet | Request::HEADER_X_FORWARDED_FOR);
            $request->headers->set('X-Forwarded-For', implode(', ', $trustedIps));
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
