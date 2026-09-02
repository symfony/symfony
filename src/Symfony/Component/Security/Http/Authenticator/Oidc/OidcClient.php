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
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Base HTTP client for OpenID Connect protocol operations.
 *
 * Concrete subclasses decide how the client authenticates at the token endpoint
 * (RFC 6749 §2.3): confidential clients send a secret, public clients rely on PKCE,
 * other profiles use signed JWTs (OIDC Core §9).
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth OIDC Core 1.0 §3.1
 * @see https://datatracker.ietf.org/doc/html/rfc6749                      OAuth 2.0 (RFC 6749)
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
abstract class OidcClient
{
    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly OidcDiscovery $discovery,
        protected readonly string $clientId,
    ) {
    }

    /**
     * Exchanges an authorization code for tokens at the token endpoint.
     *
     * @return array<string, mixed>
     *
     * @throws AuthenticationException If the token endpoint is missing, cannot be reached or returns an invalid response
     */
    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $tokenEndpoint = $this->discovery->getSecureEndpoint('token_endpoint');

        $body = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $this->clientId,
        ];

        if (null !== $codeVerifier) {
            $body['code_verifier'] = $codeVerifier;
        }

        $options = $this->applyClientAuthentication($body, []);

        try {
            return $this->httpClient->request('POST', $tokenEndpoint, $options)->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC token endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Fetches the user's claims from the OIDC provider's UserInfo endpoint.
     *
     * @return array<string, mixed>
     *
     * @throws AuthenticationException If the userinfo endpoint is missing, cannot be reached or returns an invalid response
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $userInfoEndpoint = $this->discovery->getSecureEndpoint('userinfo_endpoint');

        try {
            return $this->httpClient->request('GET', $userInfoEndpoint, [
                'auth_bearer' => $accessToken,
            ])->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC userinfo endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Applies the client authentication scheme to the token endpoint request.
     *
     * Subclasses return the final HttpClient options array (typically shaped as
     * `['body' => ..., 'auth_basic' => ...]`), starting from the given request body.
     *
     * @param array<string, mixed> $body    The token request body being built
     * @param array<string, mixed> $options The HttpClient options being built
     *
     * @return array<string, mixed> The final HttpClient options
     */
    abstract protected function applyClientAuthentication(array $body, array $options): array;
}
