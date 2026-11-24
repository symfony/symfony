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
    public static function handle(HttpKernelInterface $kernel, Request $request, int $type, bool $catch): Response
    {
        $trustedHeaderSet = Request::getTrustedHeaderSet();

        // remove untrusted values
        $remoteAddr = $request->server->get('REMOTE_ADDR');
        if (!$remoteAddr || !$request->isFromTrustedProxy()) {
            $trustedHeaders = [
                'FORWARDED' => $trustedHeaderSet & Request::HEADER_FORWARDED,
                'X_FORWARDED_FOR' => $trustedHeaderSet & Request::HEADER_X_FORWARDED_FOR,
                'X_FORWARDED_HOST' => $trustedHeaderSet & Request::HEADER_X_FORWARDED_HOST,
                'X_FORWARDED_PROTO' => $trustedHeaderSet & Request::HEADER_X_FORWARDED_PROTO,
                'X_FORWARDED_PORT' => $trustedHeaderSet & Request::HEADER_X_FORWARDED_PORT,
                'X_FORWARDED_PREFIX' => $trustedHeaderSet & Request::HEADER_X_FORWARDED_PREFIX,
            ];
            foreach (array_filter($trustedHeaders) as $name => $key) {
                $request->headers->remove($name);
                $request->server->remove('HTTP_'.$name);
            }
        }

        // compute trusted values, taking any trusted proxies into account
        $trustedIps = [];
        $trustedValues = [];
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
            $request->headers->set('Forwarded', $v = implode(', ', $trustedValues));
            $request->server->set('HTTP_FORWARDED', $v);
        }
        if (Request::HEADER_X_FORWARDED_FOR & $trustedHeaderSet) {
            $request->headers->set('X-Forwarded-For', $v = implode(', ', $trustedIps));
            $request->server->set('HTTP_X_FORWARDED_FOR', $v);
        } elseif (!(Request::HEADER_FORWARDED & $trustedHeaderSet)) {
            $trustedHeaderSet |= Request::HEADER_X_FORWARDED_FOR;
            $request->headers->set('X-Forwarded-For', $v = implode(', ', $trustedIps));
            $request->server->set('HTTP_X_FORWARDED_FOR', $v);
        }

        // fix the client IP address by setting it to 127.0.0.1,
        // which is the core responsibility of this method
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        // trust 127.0.0.1 for this request and for the ones copied from it, but not globally:
        // other requests coming from 127.0.0.1, like the main one, carry headers set by the client
        $request->server->set(Request::SUB_REQUEST_TRUSTED_HEADER_SET, $trustedHeaderSet);

        return $kernel->handle($request, $type, $catch);
    }
}
