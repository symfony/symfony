<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\OAuth2\AccessTokenType;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The bearer token of RFC 6750, which is used by whoever holds it.
 *
 * Nothing is proven when it is presented, so nothing is signed and nothing is bound: the
 * token travels in the "Authorization" header under the "Bearer" scheme (Section 2.1), and
 * the transport is what keeps it confidential.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6750 The OAuth 2.0 Authorization Framework: Bearer Token Usage
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class BearerTokenType implements AccessTokenTypeInterface
{
    public const TOKEN_TYPE = 'Bearer';

    public function getTokenType(): string
    {
        return self::TOKEN_TYPE;
    }

    /**
     * Refuses a token of another type, and accepts a response naming none.
     *
     * RFC 6749, Section 5.1 makes "token_type" required, and a provider leaving it out is
     * still handing out a token to be used as this one is, which is what clients have always
     * done with it. A provider naming another type is the case to refuse: that token carries
     * a protection this client does not implement, and presenting it as a bearer token is
     * both a failure and, for a bound token, the one thing RFC 9449, Section 7.1 has the
     * resource server reject.
     */
    public function checkTokenResponse(array $tokenResponse): void
    {
        $tokenType = $tokenResponse['token_type'] ?? null;

        if (null === $tokenType || '' === $tokenType) {
            return;
        }

        if (!\is_string($tokenType) || 0 !== strcasecmp(self::TOKEN_TYPE, $tokenType)) {
            throw new AuthenticationException(\sprintf('The OIDC provider issued an access token of the "%s" type, which this client cannot use: it presents bearer tokens (RFC 6749, Section 7.1).', \is_string($tokenType) ? $tokenType : get_debug_type($tokenType)));
        }
    }

    public function prepareTokenRequest(string $url, array $options): array
    {
        return $options;
    }

    public function presentToken(#[\SensitiveParameter] string $accessToken, string $method, string $url, array $options): array
    {
        $options['auth_bearer'] = $accessToken;

        return $options;
    }

    public function onResponse(ResponseInterface $response, string $url): bool
    {
        return false;
    }
}
