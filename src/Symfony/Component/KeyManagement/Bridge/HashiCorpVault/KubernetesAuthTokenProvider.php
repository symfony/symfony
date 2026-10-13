<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\HashiCorpVault;

use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Logs in to Vault's Kubernetes auth method with the pod's service-account token.
 *
 * The client is expected to be scoped to the Vault base URI, like the one given to {@see TransitKms}.
 *
 * The service-account token is read from disk at each login, since the kubelet rotates it.
 * The Vault token is cached in memory until 60s before its lease ends, or until half of the lease for shorter ones.
 * A token without a lease is cached until it is invalidated.
 *
 * @experimental
 */
final class KubernetesAuthTokenProvider implements TokenProviderInterface
{
    public const string DEFAULT_JWT_PATH = '/var/run/secrets/kubernetes.io/serviceaccount/token';

    private const int EXPIRY_SAFETY_MARGIN_SECONDS = 60;

    private ?string $token = null;
    private int $expiresAt = 0;

    /**
     * @param string $mountPoint The path the Kubernetes auth method is enabled at, without the "auth/" prefix
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $role,
        private readonly string $mountPoint = 'kubernetes',
        private readonly string $jwtPath = self::DEFAULT_JWT_PATH,
        private readonly ?string $namespace = null,
    ) {
    }

    public function getToken(): string
    {
        if (null !== $this->token && time() < $this->expiresAt) {
            return $this->token;
        }

        $jwt = $this->readServiceAccountToken();
        $headers = [];
        if (null !== $this->namespace) {
            $headers['X-Vault-Namespace'] = $this->namespace;
        }

        try {
            $response = $this->client->request('POST', \sprintf('auth/%s/login', implode('/', array_map(rawurlencode(...), explode('/', $this->mountPoint)))), [
                'headers' => $headers,
                'json' => ['role' => $this->role, 'jwt' => $jwt],
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Failed to reach Vault.', 0, $e);
        }

        try {
            $payload = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Vault returned a non-JSON login response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Failed to read the Vault login response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        }

        if ($status >= 300) {
            $errors = $payload['errors'] ?? [];
            $message = \is_array($errors) ? implode('; ', array_filter($errors, \is_string(...))) : '';

            throw new RuntimeException(\sprintf('Vault Kubernetes login failed (HTTP %d): "%s".', $status, $message ?: 'unknown error'));
        }

        $auth = $payload['auth'] ?? null;
        if (!\is_array($auth) || !isset($auth['client_token'], $auth['lease_duration']) || !\is_string($auth['client_token']) || '' === $auth['client_token'] || !\is_int($auth['lease_duration']) || $auth['lease_duration'] < 0) {
            throw new RuntimeException('Vault returned a malformed login response.');
        }

        $leaseDuration = $auth['lease_duration'];
        $this->expiresAt = 0 === $leaseDuration ? \PHP_INT_MAX : time() + $leaseDuration - min(self::EXPIRY_SAFETY_MARGIN_SECONDS, intdiv($leaseDuration, 2));

        return $this->token = $auth['client_token'];
    }

    public function invalidateToken(#[\SensitiveParameter] string $token): void
    {
        if ($this->token === $token) {
            $this->token = null;
            $this->expiresAt = 0;
        }
    }

    private function readServiceAccountToken(): string
    {
        $jwt = is_file($this->jwtPath) && is_readable($this->jwtPath) ? @file_get_contents($this->jwtPath) : false;
        if (false === $jwt) {
            throw new RuntimeException(\sprintf('Cannot read the Kubernetes service-account token from "%s".', $this->jwtPath));
        }

        if ('' === $jwt = trim($jwt)) {
            throw new RuntimeException(\sprintf('The Kubernetes service-account token file "%s" is empty.', $this->jwtPath));
        }

        return $jwt;
    }
}
