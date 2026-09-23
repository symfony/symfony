<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Debug;

/**
 * Describes a token for the profiler, without ever giving it away.
 *
 * A JWT is described by its decoded header and claims, never by its compact form nor
 * its signature; an opaque token by a truncated hash only, enough to tell two tokens
 * apart across requests and useless to recover any of them.
 *
 * @internal
 */
final class OidcTokenDescriber
{
    /**
     * @return array{format: 'jwt'|'jwe'|'opaque', fingerprint: string, header: ?array<string, mixed>, claims: ?array<string, mixed>, expires_at: ?int}
     */
    public static function describe(#[\SensitiveParameter] string $token): array
    {
        $description = [
            'format' => 'opaque',
            // 32 bits of a SHA-256: enough to tell whether two tokens are the same one
            'fingerprint' => substr(hash('sha256', $token), 0, 8),
            'header' => null,
            'claims' => null,
            'expires_at' => null,
        ];

        $segments = explode('.', $token);
        if (!\in_array(\count($segments), [3, 5], true) || null === $header = self::decodeSegment($segments[0])) {
            return $description;
        }

        // RFC 7516: five segments make a JWE, whose payload is encrypted
        if (5 === \count($segments)) {
            $description['format'] = 'jwe';
            $description['header'] = $header;

            return $description;
        }

        if (null === $claims = self::decodeSegment($segments[1])) {
            return $description;
        }

        $description['format'] = 'jwt';
        $description['header'] = $header;
        $description['claims'] = $claims;
        $description['expires_at'] = is_numeric($claims['exp'] ?? null) ? (int) $claims['exp'] : null;

        return $description;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeSegment(string $segment): ?array
    {
        if ('' === $segment || !\is_string($json = base64_decode(strtr($segment, '-_', '+/'), true))) {
            return null;
        }

        $decoded = json_decode($json, true);

        return \is_array($decoded) ? $decoded : null;
    }
}
