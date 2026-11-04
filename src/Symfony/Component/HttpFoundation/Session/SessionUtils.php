<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session;

/**
 * Session utility functions.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Rémon van de Kamp <rpkamp@gmail.com>
 *
 * @internal
 */
final class SessionUtils
{
    public static function isEmptySession(string $data): bool
    {
        if ('' === $data) {
            return true;
        }
        static $igbinaryEmptyData, $phpserEmptyData;
        if ($data === ($igbinaryEmptyData ??= \function_exists('igbinary_serialize') ? igbinary_serialize([]) : '')) {
            trigger_deprecation('symfony/http-foundation', '8.2', 'Using igbinary for session serialization is deprecated, use the "phpser" session serializer instead.');

            return true;
        }

        $phpserEmptyData ??= \function_exists('phpser_serialize') ? phpser_serialize([]) : '';

        // with "phpser.session_key", the data is followed by a 32-byte HMAC
        return '' !== $phpserEmptyData && str_starts_with($data, $phpserEmptyData) && \in_array(\strlen($data), [\strlen($phpserEmptyData), 32 + \strlen($phpserEmptyData)], true);
    }

    /**
     * Finds the session header amongst the headers that are to be sent, removes it, and returns
     * it so the caller can process it further.
     */
    public static function popSessionCookie(string $sessionName, #[\SensitiveParameter] string $sessionId): ?string
    {
        $sessionCookie = null;
        $sessionCookiePrefix = \sprintf(' %s=', urlencode($sessionName));
        $sessionCookieWithId = \sprintf('%s%s;', $sessionCookiePrefix, urlencode($sessionId));
        $otherCookies = [];
        foreach (headers_list() as $h) {
            if (0 !== stripos($h, 'Set-Cookie:')) {
                continue;
            }
            if (11 === strpos($h, $sessionCookiePrefix, 11)) {
                $sessionCookie = $h;

                if (11 !== strpos($h, $sessionCookieWithId, 11)) {
                    $otherCookies[] = $h;
                }
            } else {
                $otherCookies[] = $h;
            }
        }
        if (null === $sessionCookie) {
            return null;
        }

        header_remove('Set-Cookie');
        foreach ($otherCookies as $h) {
            header($h, false);
        }

        return $sessionCookie;
    }
}
