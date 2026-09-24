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
use Symfony\Component\Security\Http\OAuth2\AccessTokenType\AccessTokenTypeInterface;
use Symfony\Component\Security\Http\OAuth2\AccessTokenType\BearerTokenType;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientAuthenticationInterface;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP client for OpenID Connect protocol operations.
 *
 * How the client authenticates at the token endpoint (RFC 6749 §2.3) is a property of
 * its registration at the provider, not of this class: it is injected, so that sending
 * a secret, sending nothing at all, or signing an assertion (OIDC Core §9) are the same
 * client with a different dependency.
 *
 * What kind of access token it asks for is another such property (RFC 6749 §7.1): the type
 * says what the provider must answer, how a token is presented, and what has to be proven
 * along the way, so that a bearer token (RFC 6750) and a token bound to a key the client
 * holds (RFC 9449) are the same client with a different dependency here as well.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth OIDC Core 1.0 §3.1
 * @see https://datatracker.ietf.org/doc/html/rfc6749                      OAuth 2.0 (RFC 6749)
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcClient implements OidcClientInterface
{
    private readonly AccessTokenTypeInterface $accessTokenType;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OidcDiscovery $discovery,
        private readonly string $clientId,
        private readonly ClientAuthenticationInterface $clientAuthentication,
        ?AccessTokenTypeInterface $accessTokenType = null,
    ) {
        // the bearer token of RFC 6750 is what a client asks for unless it holds a key, and
        // naming it here keeps the type a dependency rather than an absence of one
        $this->accessTokenType = $accessTokenType ?? new BearerTokenType();
    }

    public function getClientAuthenticationMethod(): string
    {
        return $this->clientAuthentication->getMethod();
    }

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

        try {
            $tokenResponse = $this->requestToken($tokenEndpoint, $body)->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC token endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }

        $this->accessTokenType->checkTokenResponse($tokenResponse);

        return $tokenResponse;
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
            $response = $this->requestToken($tokenEndpoint, $body);

            // RFC 6749, Section 5.2: "invalid_grant" is the one error saying the refresh
            // token itself is gone, where every other failure only means the request may
            // be tried again; the status code is checked first so that a provider error
            // page is never parsed as a token response
            $statusCode = $response->getStatusCode();
            if (400 <= $statusCode && $statusCode < 500 && 'invalid_grant' === ($response->toArray(false)['error'] ?? null)) {
                throw new OidcInvalidGrantException('The OIDC provider rejected the refresh token: it expired, it was revoked, or it was issued to another client.');
            }

            $tokenResponse = $response->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC token endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }

        $this->accessTokenType->checkTokenResponse($tokenResponse);

        return $tokenResponse;
    }

    public function fetchUserInfo(string $accessToken): array
    {
        $userInfoEndpoint = $this->discovery->getSecureEndpoint('userinfo_endpoint');

        try {
            return $this->send($userInfoEndpoint, fn (): ResponseInterface => $this->httpClient->request(
                'GET',
                $userInfoEndpoint,
                $this->accessTokenType->presentToken($accessToken, 'GET', $userInfoEndpoint, ['max_redirects' => 0]),
            ))->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC userinfo endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Makes a token endpoint request, with a fresh client authentication each time it is sent.
     *
     * A request that has to be sent again is authenticated again rather than repeated: a
     * client assertion carries a "jti" a provider remembers until it expires (RFC 7523,
     * Section 3), so sending the same one twice is a replay of it.
     *
     * @param array<string, string> $body
     */
    private function requestToken(string $tokenEndpoint, array $body): ResponseInterface
    {
        return $this->send($tokenEndpoint, function () use ($tokenEndpoint, $body): ResponseInterface {
            $options = $this->clientAuthentication->authenticate($this->clientId, $tokenEndpoint, ['body' => $body]);
            $options['max_redirects'] = 0;
            $options = $this->accessTokenType->prepareTokenRequest($tokenEndpoint, $options);

            return $this->httpClient->request('POST', $tokenEndpoint, $options);
        });
    }

    /**
     * Sends a request, once more when the type says the answer asks for it.
     *
     * A server may answer a first request with what the next one has to carry, and the type
     * is what knows it: it reads every response, keeps what it named, and says whether the
     * request is to be built and sent again.
     *
     * @param \Closure(): ResponseInterface $send
     */
    private function send(string $url, \Closure $send): ResponseInterface
    {
        $response = $send();

        // at most one repeat: a server that named what the next request must carry answers
        // the request carrying it, and a second refusal is a refusal and not an instruction
        if ($this->accessTokenType->onResponse($response, $url)) {
            $response = $send();
            $this->accessTokenType->onResponse($response, $url);
        }

        return $response;
    }
}
