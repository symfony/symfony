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
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for OpenID Connect protocol operations.
 *
 * How the client authenticates at the token endpoint (RFC 6749 §2.3) is a property of
 * its registration at the provider, not of this class: it is injected, so that sending
 * a secret, sending nothing at all, or signing an assertion (OIDC Core §9) are the same
 * client with a different dependency.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth OIDC Core 1.0 §3.1
 * @see https://datatracker.ietf.org/doc/html/rfc6749                      OAuth 2.0 (RFC 6749)
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcClient implements OidcClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OidcDiscovery $discovery,
        private readonly string $clientId,
        private readonly ClientAuthenticationInterface $clientAuthentication,
    ) {
    }

    public function getClientAuthenticationMethod(): string
    {
        return $this->clientAuthentication->getMethod();
    }

    public function exchangeCode(#[\SensitiveParameter] string $code, string $redirectUri, #[\SensitiveParameter] ?string $codeVerifier = null): array
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

        try {
            return $this->httpClient->request('POST', $tokenEndpoint, $this->createTokenRequestOptions($tokenEndpoint, $body))->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC token endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }

    public function refreshToken(#[\SensitiveParameter] string $refreshToken, array $scopes = []): array
    {
        $tokenEndpoint = $this->discovery->getSecureEndpoint('token_endpoint');

        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
        ];

        if ($scopes) {
            $body['scope'] = implode(' ', $scopes);
        }

        try {
            $response = $this->httpClient->request('POST', $tokenEndpoint, $this->createTokenRequestOptions($tokenEndpoint, $body));

            // RFC 6749, Section 5.2: "invalid_grant" is the one error saying the refresh
            // token itself is gone, where every other failure only means the request may
            // be tried again; the status code is checked first so that a provider error
            // page is never parsed as a token response
            $statusCode = $response->getStatusCode();
            if (400 <= $statusCode && $statusCode < 500 && 'invalid_grant' === ($response->toArray(false)['error'] ?? null)) {
                // the rejection travels as the cause, so that whoever handles the
                // exception can still read the "error_description" of the response
                $previous = null;
                try {
                    $response->getHeaders();
                } catch (ClientExceptionInterface $previous) {
                    // a 4xx status always throws here
                }

                throw new OidcInvalidGrantException('The OIDC provider rejected the refresh token: it expired, it was revoked, or it was issued to another client.', previous: $previous);
            }

            return $response->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC token endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Builds the options of a token request, authenticated the way the client is configured to.
     *
     * The response carries bearer credentials, so the HttpClient profiler panel is told to
     * keep neither it nor the request body: "extra.trace_content" is read by the traceable
     * client the profiler decorates every client with, and ignored by every other client.
     *
     * @param array<string, string> $body
     *
     * @return array<string, mixed>
     */
    private function createTokenRequestOptions(string $tokenEndpoint, #[\SensitiveParameter] array $body): array
    {
        $options = $this->clientAuthentication->authenticate($this->clientId, $tokenEndpoint, ['body' => $body]);
        $options['max_redirects'] = 0;
        $options['extra']['trace_content'] = false;

        return $options;
    }

    public function fetchUserInfo(#[\SensitiveParameter] string $accessToken): array
    {
        $userInfoEndpoint = $this->discovery->getSecureEndpoint('userinfo_endpoint');

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
