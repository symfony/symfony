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
use Symfony\Component\Security\Http\OAuth2\AccessTokenType\DpopTokenType;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientAuthenticationInterface;
use Symfony\Component\Security\Http\OAuth2\Dpop\DpopProofFactory;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP client for OpenID Connect protocol operations.
 *
 * How the client authenticates at the token endpoint (RFC 6749 §2.3) is a property of
 * its registration at the provider, not of this class: it is injected, so that sending
 * a secret, sending nothing at all, signing an assertion (OIDC Core §9) or letting the
 * provider read the certificate of the TLS handshake (RFC 8705 §2) are the same client
 * with a different dependency.
 *
 * The client certificate is not part of that dependency, since a client may present one without authenticating with it (RFC 8705 §4).
 * It is sent with each request made here, and these requests then go to the mutual-TLS aliases of the endpoints (RFC 8705 §5).
 *
 * What kind of access token it asks for is another such property (RFC 6749 §7.1): a bearer token (RFC 6750),
 * or a token bound to the key a DPoP proof factory signs with (RFC 9449) when one is given.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth OIDC Core 1.0 §3.1
 * @see https://datatracker.ietf.org/doc/html/rfc6749                      OAuth 2.0 (RFC 6749)
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcClient implements OidcClientInterface
{
    private readonly AccessTokenTypeInterface $accessTokenType;

    /**
     * @param array<string, mixed> $certificateOptions The "local_cert", "local_pk" and "passphrase" HTTP client options of the client certificate, or none
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OidcDiscovery $discovery,
        private readonly string $clientId,
        private readonly ClientAuthenticationInterface $clientAuthentication,
        private readonly array $certificateOptions = [],
        ?DpopProofFactory $dpopProofFactory = null,
    ) {
        $this->accessTokenType = null === $dpopProofFactory ? new BearerTokenType() : new DpopTokenType($dpopProofFactory);
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
        $tokenEndpoint = $this->endpoint('token_endpoint');

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
        $userInfoEndpoint = $this->endpoint('userinfo_endpoint');

        try {
            return $this->send($userInfoEndpoint, fn (): ResponseInterface => $this->httpClient->request(
                'GET',
                $userInfoEndpoint,
                $this->certificateOptions + $this->accessTokenType->presentToken($accessToken, 'GET', $userInfoEndpoint, ['max_redirects' => 0]),
            ))->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC userinfo endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Returns the URL of an endpoint, or of its mutual-TLS alias when the client presents a certificate and the provider announces one (RFC 8705, Section 5).
     */
    private function endpoint(string $endpoint): string
    {
        $aliases = $this->certificateOptions ? $this->discovery->getConfiguration()['mtls_endpoint_aliases'] ?? null : null;

        if (!\is_array($aliases) || !\array_key_exists($endpoint, $aliases)) {
            return $this->discovery->getSecureEndpoint($endpoint);
        }

        // checked again here, as the document may have been cached by a discovery checking no alias
        if (!\is_string($alias = $aliases[$endpoint]) || !OidcDiscovery::isSecureUrl($alias)) {
            throw new AuthenticationException(\sprintf('The "mtls_endpoint_aliases.%s" announced by the OIDC provider must be an HTTPS URL.', $endpoint));
        }

        return $alias;
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

            return $this->httpClient->request('POST', $tokenEndpoint, $this->certificateOptions + $options);
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
