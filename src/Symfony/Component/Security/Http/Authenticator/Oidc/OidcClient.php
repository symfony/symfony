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
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientAuthenticationInterface;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for OpenID Connect protocol operations.
 *
 * How the client authenticates at the token endpoint (RFC 6749 §2.3) is a property of
 * its registration at the provider, not of this class: it is injected, so that sending
 * a secret, sending nothing at all, signing an assertion (OIDC Core §9) or letting the
 * provider read the certificate of the handshake (RFC 8705 §2) are the same client with a
 * different dependency. The certificate itself is carried by the HTTP client and not by
 * that dependency, because a client may present one without authenticating with it
 * (RFC 8705 §4); what it changes here is the endpoints the requests are made to
 * (RFC 8705 §5).
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth OIDC Core 1.0 §3.1
 * @see https://datatracker.ietf.org/doc/html/rfc6749                      OAuth 2.0 (RFC 6749)
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcClient implements OidcClientInterface
{
    /**
     * @param bool $mutualTls Whether the HTTP client presents a client certificate to the provider,
     *                        which is what decides the endpoints of RFC 8705, Section 5 are used
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OidcDiscovery $discovery,
        private readonly string $clientId,
        private readonly ClientAuthenticationInterface $clientAuthentication,
        private readonly bool $mutualTls = false,
    ) {
    }

    /**
     * Resolves an endpoint of the provider, under its mutual-TLS alias when one is used.
     *
     * A client intending to do mutual TLS, to authenticate itself or only to have its tokens
     * bound to its certificate, must use the endpoints the provider publishes for it
     * (RFC 8705, Section 5); which of the two it does is not something this class knows, and
     * carrying a certificate at all is the intent the specification names.
     */
    private function endpoint(string $endpoint): string
    {
        return $this->mutualTls
            ? $this->discovery->getSecureMutualTlsEndpoint($endpoint)
            : $this->discovery->getSecureEndpoint($endpoint);
    }

    public function getClientAuthenticationMethod(): string
    {
        return $this->clientAuthentication->getMethod();
    }

    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $tokenEndpoint = $this->endpoint('token_endpoint');

        $body = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $this->clientId,
        ];

        if (null !== $codeVerifier) {
            $body['code_verifier'] = $codeVerifier;
        }

        $options = $this->clientAuthentication->authenticate($this->clientId, $tokenEndpoint, ['body' => $body]);
        $options['max_redirects'] = 0;

        try {
            return $this->httpClient->request('POST', $tokenEndpoint, $options)->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC token endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }

    public function refreshToken(#[\SensitiveParameter] string $refreshToken, array $scopes = []): array
    {
        $tokenEndpoint = $this->endpoint('token_endpoint');

        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
        ];

        if ($scopes) {
            $body['scope'] = implode(' ', $scopes);
        }

        $options = $this->clientAuthentication->authenticate($this->clientId, $tokenEndpoint, ['body' => $body]);
        $options['max_redirects'] = 0;

        try {
            $response = $this->httpClient->request('POST', $tokenEndpoint, $options);

            // RFC 6749, Section 5.2: "invalid_grant" is the one error saying the refresh
            // token itself is gone, where every other failure only means the request may
            // be tried again; the status code is checked first so that a provider error
            // page is never parsed as a token response
            $statusCode = $response->getStatusCode();
            if (400 <= $statusCode && $statusCode < 500 && 'invalid_grant' === ($response->toArray(false)['error'] ?? null)) {
                throw new OidcInvalidGrantException('The OIDC provider rejected the refresh token: it expired, it was revoked, or it was issued to another client.');
            }

            return $response->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC token endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Reads the user claims from the UserInfo endpoint, a protected resource.
     *
     * The access token is what authorizes the request, so the client authentication has no say
     * in it. A client carrying a certificate still reaches the endpoint under its alias and
     * presents that certificate, because a provider that asked for one binds the access token
     * to it (RFC 8705, Section 3) and the bearer token alone then gets the client nowhere; the
     * HTTP client carries it, so nothing has to be added to the request here.
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $userInfoEndpoint = $this->endpoint('userinfo_endpoint');

        try {
            return $this->httpClient->request('GET', $userInfoEndpoint, [
                'auth_bearer' => $accessToken,
                'max_redirects' => 0,
            ])->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC userinfo endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }
}
