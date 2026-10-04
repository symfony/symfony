<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Oidc;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Exception\OidcInvalidGrantException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The OpenID Connect protocol operations the login flow needs from a provider.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth OIDC Core 1.0 §3.1
 * @see https://datatracker.ietf.org/doc/html/rfc6749                      OAuth 2.0 (RFC 6749)
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
interface OidcClientInterface
{
    /**
     * Makes a client-authenticated POST to an endpoint the provider announces, which the grants
     * below are written on, as is anything this interface does not name: revoking a token,
     * introspecting one, pushing an authorization request, or running a grant of its own.
     *
     * The raw response is returned, an endpoint being free to answer a JSON object, a signed JWT,
     * or nothing at all, as RFC 7009, Section 2.2 makes the answer of a revocation.
     *
     * @param string               $endpoint The metadata member naming the endpoint, e.g. "revocation_endpoint"
     * @param array<string, mixed> $body     The form body, which the "client_id" is added to
     * @param array<string, mixed> $options  The HttpClient options, over which the body, the
     *                                       redirects and what the registration contributes win
     *
     * @throws AuthenticationException If the endpoint is not announced by the provider, or does not use HTTPS
     */
    public function request(string $endpoint, array $body = [], array $options = []): ResponseInterface;

    /**
     * Exchanges an authorization code for tokens at the token endpoint.
     *
     * @return array<string, mixed>
     *
     * @throws AuthenticationException If the token endpoint is missing, cannot be reached or returns an invalid response
     */
    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): array;

    /**
     * Renews an access token with the refresh token grant of RFC 6749, Section 6.
     *
     * The provider may answer with a new refresh token, which then replaces the one
     * given here, and with a new ID token, which OIDC Core 1.0, Section 12.2 constrains.
     *
     * @param list<string> $scopes The scopes of the new access token, which RFC 6749,
     *                             Section 6 only allows to narrow the ones the refresh
     *                             token was issued with; the original scopes are asked
     *                             for when the list is empty
     *
     * @return array<string, mixed>
     *
     * @throws OidcInvalidGrantException If the provider no longer honors the refresh token
     * @throws AuthenticationException   If the token endpoint is missing, cannot be reached or returns an invalid response
     */
    public function refreshToken(#[\SensitiveParameter] string $refreshToken, array $scopes = []): array;

    /**
     * Fetches the user's claims from the OIDC provider's UserInfo endpoint.
     *
     * @return array<string, mixed>
     *
     * @throws AuthenticationException If the userinfo endpoint is missing, cannot be reached or returns an invalid response
     */
    public function fetchUserInfo(string $accessToken): array;

    /**
     * Returns the name of the method the client authenticates with at the token endpoint.
     *
     * The names are the ones RFC 7591, Section 2 registers, e.g. "client_secret_basic", or
     * "none" for a public client.
     *
     * Whether the client holds a secret is what the security of the whole flow rests on,
     * so the flow is entitled to ask, and to refuse to run with a configuration a public
     * client cannot afford.
     */
    public function getClientAuthenticationMethod(): string;
}
