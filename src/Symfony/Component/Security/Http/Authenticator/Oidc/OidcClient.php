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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implements the OpenID Connect Authorization Code Flow protocol.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth
 *
 * @author Mathieu Music <music.music@gmail.com>
 */
final class OidcClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OidcDiscovery $discovery,
        private readonly OidcAuthorizationCodeFlowState $stateStorage,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $callbackUrl,
        private readonly array $scopes = ['openid'],
        private readonly bool $pkceEnabled = true,
        private readonly string $pkceMethod = 'S256',
        private readonly string $tokenEndpointAuthMethod = 'client_secret_post',
    ) {
    }

    /**
     * Builds the authorization URL and returns a redirect response to the OIDC provider.
     *
     * Generates and stores state, nonce, and optionally PKCE parameters in the session.
     *
     * @param array<string, string> $extraParams Additional query parameters for the authorization URL
     */
    public function startAuthorization(array $extraParams = []): RedirectResponse
    {
        $configuration = $this->discovery->getConfiguration();

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));

        $this->stateStorage->setState($state);
        $this->stateStorage->setNonce($nonce);

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->callbackUrl,
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($this->pkceEnabled) {
            $codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
            $this->stateStorage->setCodeVerifier($codeVerifier);

            if ('S256' === $this->pkceMethod) {
                $params['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
                $params['code_challenge_method'] = 'S256';
            } else {
                $params['code_challenge'] = $codeVerifier;
                $params['code_challenge_method'] = 'plain';
            }
        }

        $params = array_merge($params, $extraParams);

        $authorizationUrl = $configuration->authorizationEndpoint
            .(str_contains($configuration->authorizationEndpoint, '?') ? '&' : '?')
            .http_build_query($params, '', '&', \PHP_QUERY_RFC3986);

        return new RedirectResponse($authorizationUrl);
    }

    /**
     * Handles the callback from the OIDC provider by validating the state
     * and exchanging the authorization code for tokens.
     *
     * @throws AuthenticationException If state validation fails or token exchange fails
     */
    public function handleCallback(Request $request): OidcTokens
    {
        $error = $request->query->get('error');
        if (null !== $error) {
            $description = $request->query->get('error_description', $error);
            throw new AuthenticationException(\sprintf('OIDC provider returned an error: %s', $description));
        }

        $state = $request->query->get('state');
        $storedState = $this->stateStorage->getState();

        if (null === $state || !hash_equals((string) $storedState, $state)) {
            throw new AuthenticationException('Invalid OIDC state parameter.');
        }

        $code = $request->query->get('code');
        if (null === $code) {
            throw new AuthenticationException('Missing authorization code in OIDC callback.');
        }

        try {
            $tokens = $this->exchangeCode($code);
        } finally {
            $this->stateStorage->clear();
        }

        return $tokens;
    }

    /**
     * Fetches the user's claims from the OIDC provider's UserInfo endpoint.
     *
     * @return array<string, mixed> The user's claims
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $configuration = $this->discovery->getConfiguration();

        if (null === $configuration->userinfoEndpoint) {
            throw new \LogicException('The OIDC provider does not expose a userinfo endpoint.');
        }

        return $this->httpClient->request('GET', $configuration->userinfoEndpoint, [
            'auth_bearer' => $accessToken,
        ])->toArray();
    }

    /**
     * Refreshes the tokens using a refresh token.
     */
    public function refreshTokens(string $refreshToken): OidcTokens
    {
        $configuration = $this->discovery->getConfiguration();

        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
        ];

        $options = $this->buildTokenRequestOptions($body, $configuration);
        $data = $this->httpClient->request('POST', $configuration->tokenEndpoint, $options)->toArray();

        return OidcTokens::fromTokenEndpointResponse($data);
    }

    /**
     * Builds the end-session (RP-Initiated Logout) URL.
     *
     * @see https://openid.net/specs/openid-connect-rpinitiated-1_0.html
     */
    public function buildEndSessionUrl(string $idToken, ?string $postLogoutRedirectUri = null): string
    {
        $configuration = $this->discovery->getConfiguration();

        if (null === $configuration->endSessionEndpoint) {
            throw new \LogicException('The OIDC provider does not expose an end_session_endpoint.');
        }

        $params = ['id_token_hint' => $idToken];

        if (null !== $postLogoutRedirectUri) {
            $params['post_logout_redirect_uri'] = $postLogoutRedirectUri;
        }

        return $configuration->endSessionEndpoint.'?'.http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    private function exchangeCode(string $code): OidcTokens
    {
        $configuration = $this->discovery->getConfiguration();

        $body = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->callbackUrl,
            'client_id' => $this->clientId,
        ];

        $codeVerifier = $this->stateStorage->getCodeVerifier();
        if (null !== $codeVerifier) {
            $body['code_verifier'] = $codeVerifier;
        }

        $options = $this->buildTokenRequestOptions($body, $configuration);
        $data = $this->httpClient->request('POST', $configuration->tokenEndpoint, $options)->toArray();

        return OidcTokens::fromTokenEndpointResponse($data);
    }

    /**
     * @param array<string, string> $body
     *
     * @return array<string, mixed>
     */
    private function buildTokenRequestOptions(array $body, OidcConfiguration $configuration): array
    {
        if ('client_secret_basic' === $this->tokenEndpointAuthMethod) {
            return [
                'auth_basic' => [$this->clientId, $this->clientSecret],
                'body' => $body,
            ];
        }

        // Default: client_secret_post
        $body['client_secret'] = $this->clientSecret;

        return ['body' => $body];
    }
}
