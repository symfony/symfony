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
        $trustedHeaders = array(
            Request::HEADER_FORWARDED => Request::getTrustedHeaderName(Request::HEADER_FORWARDED),
            Request::HEADER_CLIENT_IP => Request::getTrustedHeaderName(Request::HEADER_CLIENT_IP),
            Request::HEADER_CLIENT_HOST => Request::getTrustedHeaderName(Request::HEADER_CLIENT_HOST),
            Request::HEADER_CLIENT_PROTO => Request::getTrustedHeaderName(Request::HEADER_CLIENT_PROTO),
            Request::HEADER_CLIENT_PORT => Request::getTrustedHeaderName(Request::HEADER_CLIENT_PORT),
        );

        // remove untrusted values
        $remoteAddr = $request->server->get('REMOTE_ADDR');
        if (!IpUtils::checkIp($remoteAddr, $trustedProxies)) {
            foreach (array_filter($trustedHeaders) as $name) {
                $request->headers->remove($name);
                $request->server->remove('HTTP_'.strtoupper(str_replace('-', '_', $name)));
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
            $request->headers->set($name, $v = implode(', ', $trustedValues));
            $request->server->set('HTTP_'.strtoupper(str_replace('-', '_', $name)), $v);
        }
        if ($name = $trustedHeaders[Request::HEADER_CLIENT_IP]) {
            $request->headers->set($name, $v = implode(', ', $trustedIps));
            $request->server->set('HTTP_'.strtoupper(str_replace('-', '_', $name)), $v);
        }
        if (!$name && !$trustedHeaders[Request::HEADER_FORWARDED]) {
            $request->headers->set('X-Forwarded-For', $v = implode(', ', $trustedIps));
            $request->server->set('HTTP_X_FORWARDED_FOR', $v);
            Request::setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');
        }

        // fix the client IP address by setting it to 127.0.0.1,
        // which is the core responsibility of this method
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        // ensure 127.0.0.1 is set as trusted proxy
        if (!IpUtils::checkIp('127.0.0.1', $trustedProxies)) {
            Request::setTrustedProxies(array_merge($trustedProxies, array('127.0.0.1')));
        }

        try {
            $e = null;
            $response = $kernel->handle($request, $type, $catch);
        } catch (\Throwable $e) {
        } catch (\Exception $e) {
        }

        // restore global state
        Request::setTrustedHeaderName(Request::HEADER_CLIENT_IP, $trustedHeaders[Request::HEADER_CLIENT_IP]);
        Request::setTrustedProxies($trustedProxies);

        if (null !== $e) {
            throw $e;
        }

        return $response;
    }
}
