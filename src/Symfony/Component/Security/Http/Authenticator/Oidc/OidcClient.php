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
 * a secret, sending nothing at all, or signing an assertion (OIDC Core §9) are the same
 * client with a different dependency.
 *
 * Whether what it is given is bound to a key it holds (RFC 9449) is another such property:
 * given a proof factory, every request made here carries a proof of the key, and the access
 * token comes back bound to it.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth OIDC Core 1.0 §3.1
 * @see https://datatracker.ietf.org/doc/html/rfc6749                      OAuth 2.0 (RFC 6749)
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcClient implements OidcClientInterface
{
    /**
     * The nonce the provider last named, RFC 9449, Section 8.
     *
     * It is held for the requests that follow in the same PHP request, the login flow making
     * several: the provider answers the first one with the nonce it wants, and the token
     * request, the refresh and the UserInfo call that follow carry it without asking again.
     */
    private ?string $dpopNonce = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OidcDiscovery $discovery,
        private readonly string $clientId,
        private readonly ClientAuthenticationInterface $clientAuthentication,
        private readonly ?DpopProofFactory $dpopProofFactory = null,
    ) {
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
            return $this->requestToken($tokenEndpoint, $body)->toArray();
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
            $response = $this->requestToken($tokenEndpoint, $body);

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

    public function fetchUserInfo(string $accessToken): array
    {
        $userInfoEndpoint = $this->discovery->getSecureEndpoint('userinfo_endpoint');

        try {
            return $this->send(function (?string $nonce) use ($userInfoEndpoint, $accessToken): ResponseInterface {
                $options = ['max_redirects' => 0];

                if (null === $this->dpopProofFactory) {
                    $options['auth_bearer'] = $accessToken;
                } else {
                    // RFC 9449, Section 7.1: a token bound to a key is presented under the
                    // "DPoP" scheme, and a provider refuses it under "Bearer"
                    $options['headers']['Authorization'] = DpopProofFactory::SCHEME.' '.$accessToken;
                    $options['headers']['DPoP'] = $this->dpopProofFactory->createProof('GET', $userInfoEndpoint, $accessToken, $nonce);
                }

                return $this->httpClient->request('GET', $userInfoEndpoint, $options);
            })->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC userinfo endpoint request failed: "%s"', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Makes a token endpoint request, with a fresh client authentication each time it is sent.
     *
     * A request that has to be sent again for a nonce is authenticated again rather than
     * repeated: a client assertion carries a "jti" a provider remembers until it expires
     * (RFC 7523, Section 3), so sending the same one twice is a replay of it.
     *
     * @param array<string, string> $body
     */
    private function requestToken(string $tokenEndpoint, array $body): ResponseInterface
    {
        return $this->send(function (?string $nonce) use ($tokenEndpoint, $body): ResponseInterface {
            $options = $this->clientAuthentication->authenticate($this->clientId, $tokenEndpoint, ['body' => $body]);
            $options['max_redirects'] = 0;

            if (null !== $this->dpopProofFactory) {
                $options['headers']['DPoP'] = $this->dpopProofFactory->createProof('POST', $tokenEndpoint, null, $nonce);
            }

            return $this->httpClient->request('POST', $tokenEndpoint, $options);
        });
    }

    /**
     * Sends a request, once more with the nonce when the provider answers that it wants one.
     *
     * RFC 9449, Section 8: a provider may refuse a proof until it carries a nonce of its own,
     * which is the one thing it knows that the client cannot have chosen. It names the nonce
     * in a header of that refusal, and of any later response, so the value is kept for the
     * requests that follow instead of being asked for again.
     *
     * @param \Closure(?string): ResponseInterface $send
     */
    private function send(\Closure $send): ResponseInterface
    {
        $response = $send($this->dpopNonce);

        if (null === $this->dpopProofFactory) {
            return $response;
        }

        $previousNonce = $this->dpopNonce;
        $this->readNonce($response);

        // sending the same proof again would be refused for the same reason, so the retry
        // only happens once the provider has named a nonce this client did not already use
        if (self::wantsNonce($response) && null !== $this->dpopNonce && $this->dpopNonce !== $previousNonce) {
            $response = $send($this->dpopNonce);
            $this->readNonce($response);
        }

        return $response;
    }

    private function readNonce(ResponseInterface $response): void
    {
        $nonce = $response->getHeaders(false)[DpopProofFactory::NONCE_HEADER][0] ?? null;

        if (\is_string($nonce) && '' !== $nonce) {
            $this->dpopNonce = $nonce;
        }
    }

    private static function wantsNonce(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();

        if (400 !== $statusCode && 401 !== $statusCode) {
            return false;
        }

        // the token endpoint says it in the error of a JSON body, a resource such as the
        // UserInfo endpoint in the challenge it answers with
        if (str_contains(implode(' ', $response->getHeaders(false)['www-authenticate'] ?? []), 'use_dpop_nonce')) {
            return true;
        }

        try {
            return 'use_dpop_nonce' === ($response->toArray(false)['error'] ?? null);
        } catch (HttpClientExceptionInterface) {
            // an error page rather than the JSON the endpoint owes, which says nothing
            // about a nonce and is reported by the caller reading the response
            return false;
        }
    }
}
