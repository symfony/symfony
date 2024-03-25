<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Bridge\OAuth;

use Symfony\Component\AccessToken\AccessToken;
use Symfony\Component\AccessToken\AccessTokenInterface;
use Symfony\Component\AccessToken\CredentialsInterface;
use Symfony\Component\AccessToken\Bridge\AbstractProvider;
use Symfony\Component\AccessToken\Exception\ProviderFetchException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OAuth2 "refresh_token" provider.
 *
 * This provider implements strictly OAuth 2.0 standard, and should work with all
 * remote services that implement it correctly.
 *
 * Depending upon the remote provider, "client_credentials" and "authorization_code"
 * given tokens could be refreshed. Most will allow it only with "authorization_code"
 * tokens (for example, Microsoft).
 *
 * This implementation was tested with:
 *   - None yet.
 *
 * @todo Test with Microsoft
 * @todo Test with Orange
 * @todo Test with Google
 * @todo Test with Salesforce
 * @todo Test with ... ?
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class RefreshTokenProvider extends AbstractProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function supports(CredentialsInterface $credentials): bool
    {
        return $credentials instanceof RefreshTokenCredentials;
    }

    protected function fetchToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        \assert($credentials instanceof RefreshTokenCredentials);

        if (!$endpointUrl = ($credentials->getEndpoint() ?? $this->getDefaultEndpointUrl($credentials))) {
            throw new ProviderFetchException('OAuth2 credentials are missing the endpoint URL.');
        }

        $response = $this->httpClient->request('POST', $endpointUrl, [
            'query' => $this->getQuery($credentials),
            'headers' => $this->getHeaders($credentials),
            'body' => $this->getBody($credentials),
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderFetchException(sprintf('OAuth2 token could not be fetched from "%s": "%s".', $endpointUrl, $response->getContent(false)));
        }

        return $this->parseResponse($credentials, $response->getContent());
    }

    /**
     * Get or build default authorization endpoint URL.
     *
     * You may override this in child classes.
     */
    protected function getDefaultEndpointUrl(RefreshTokenCredentials $credentials): ?string
    {
        return null;
    }

    /**
     * Get default HTTP request headers for authorization request.
     *
     * You may override this in child classes.
     */
    protected function getHeaders(RefreshTokenCredentials $credentials): array
    {
        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    /**
     * Build additional GET parameters for authorization request.
     *
     * You may override this in child classes.
     */
    protected function getQuery(RefreshTokenCredentials $credentials): array
    {
        return [];
    }

    /**
     * Build body parameters for authorization request.
     *
     * You may override this in child classes.
     */
    protected function getBody(RefreshTokenCredentials $credentials): array
    {
        return array_filter([
            'refresh_token' => $credentials->getRefreshToken(),
            'client_id' => $credentials->getClientId(),
            'client_secret' => $credentials->getClientSecret(),
            'scope' => $credentials->getScopeAsString(),
            'grant_type' => $credentials->getGrantType(),
        ]);
    }

    /**
     * Parse response body and create access token.
     *
     * You may override this in child classes.
     */
    protected function parseResponse(RefreshTokenCredentials $credentials, string $body): AccessTokenInterface
    {
        try {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ProviderFetchException(sprintf('OAuth2 token response is not JSON: "%s".', $body), 0, $e);
        }

        if (!isset($data['access_token'])) {
            throw new ProviderFetchException(sprintf('OAuth2 token is missing from response: "%s".', $body));
        }

        return new AccessToken(
            value: $data['access_token'],
            type: $data['token_type'] ?? 'Bearer',
            expiresIn: (int) ($data['expires_in'] ?? $credentials->getDefaultLifetime()),
            id: $credentials->getId(),
        );
    }
}
