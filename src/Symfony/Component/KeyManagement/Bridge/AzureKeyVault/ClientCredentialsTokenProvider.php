<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AzureKeyVault;

use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Acquires Azure AD bearer tokens for the Azure Key Vault audience using the
 * OAuth2 `client_credentials` grant: tenant id + client id + client secret.
 *
 * The token is cached in memory until 60s before its advertised expiration to
 * give long-running operations a safety margin against clock skew.
 *
 * Deployments using Managed Identity, Workload Identity, federated credentials
 * or any other flow should implement {@see TokenProviderInterface} directly
 * rather than going through this class.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class ClientCredentialsTokenProvider implements TokenProviderInterface
{
    private const string DEFAULT_AUTHORITY = 'https://login.microsoftonline.com';
    private const string DEFAULT_AUDIENCE = 'https://vault.azure.net/.default';
    private const int EXPIRY_SAFETY_MARGIN_SECONDS = 60;

    private ?string $token = null;
    private int $expiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $tenantId,
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        private readonly string $audience = self::DEFAULT_AUDIENCE,
        private readonly string $authority = self::DEFAULT_AUTHORITY,
    ) {
    }

    public function getToken(): string
    {
        if (null !== $this->token && time() < $this->expiresAt) {
            return $this->token;
        }

        $url = \sprintf('%s/%s/oauth2/v2.0/token', rtrim($this->authority, '/'), rawurlencode($this->tenantId));

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => $this->audience,
                ]),
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Failed to reach the Azure AD token endpoint.', 0, $e);
        }

        try {
            $payload = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new RuntimeException(\sprintf('The Azure AD token endpoint returned a non-JSON response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        }

        if ($status >= 400) {
            $description = (string) ($payload['error_description'] ?? $payload['error'] ?? 'unknown error');
            throw new RuntimeException(\sprintf('Azure AD token request failed (HTTP %d): "%s".', $status, $description));
        }

        if ($status >= 300) {
            throw new RuntimeException(\sprintf('Azure AD token request failed (HTTP %d) for "%s".', $status, $response->getInfo('url')));
        }

        if (!isset($payload['access_token'], $payload['expires_in']) || !\is_string($payload['access_token']) || !is_numeric($payload['expires_in'])) {
            throw new RuntimeException('Azure AD returned a malformed token response.');
        }

        $this->token = $payload['access_token'];
        $this->expiresAt = time() + (int) $payload['expires_in'] - self::EXPIRY_SAFETY_MARGIN_SECONDS;

        return $this->token;
    }
}
