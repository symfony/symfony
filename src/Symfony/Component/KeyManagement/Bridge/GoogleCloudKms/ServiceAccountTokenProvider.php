<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\GoogleCloudKms;

use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\KeyMaterial;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Acquires Google Cloud OAuth2 tokens using the `service_account` credentials
 * flow: a JWT is signed with the service account's RSA private key (RS256)
 * and exchanged at the Google token endpoint for an access token.
 *
 * The token is cached in memory until 60s before its advertised expiration to
 * give long-running operations a safety margin against clock skew.
 *
 * Deployments running on GCE/GKE/Cloud Run/Cloud Functions should implement
 * {@see TokenProviderInterface} against the metadata server rather than going
 * through this class.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class ServiceAccountTokenProvider implements TokenProviderInterface
{
    use KeyMaterial;

    private const string DEFAULT_SCOPE = 'https://www.googleapis.com/auth/cloudkms';
    private const string DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const string GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:jwt-bearer';
    private const int EXPIRY_SAFETY_MARGIN_SECONDS = 60;

    /**
     * How far in the past the JWT `iat` claim is set, so a local clock slightly
     * ahead of Google's does not make the assertion "issued in the future".
     */
    private const int IAT_CLOCK_SKEW_SECONDS = 10;

    private ?string $token = null;
    private int $expiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $clientEmail,
        #[\SensitiveParameter] string $privateKeyPem,
        private readonly string $tokenUri = self::DEFAULT_TOKEN_URI,
        private readonly string $scope = self::DEFAULT_SCOPE,
    ) {
        if (!\extension_loaded('openssl')) {
            throw new LogicException('The "openssl" PHP extension is required to sign Google service-account JWTs.');
        }

        $this->keepMaterial($privateKeyPem);
    }

    /**
     * @param string $path Path to a service-account JSON key file as downloaded from the GCP console
     */
    public static function fromJsonFile(HttpClientInterface $client, string $path, string $scope = self::DEFAULT_SCOPE): self
    {
        $contents = @file_get_contents($path);
        if (false === $contents) {
            throw new InvalidArgumentException(\sprintf('Cannot read service-account JSON file "%s".', $path));
        }

        return self::fromJsonString($client, $contents, $scope);
    }

    public static function fromJsonString(HttpClientInterface $client, #[\SensitiveParameter] string $json, string $scope = self::DEFAULT_SCOPE): self
    {
        try {
            $payload = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Service-account JSON is not valid JSON.', 0, $e);
        }
        if (!\is_array($payload)) {
            throw new InvalidArgumentException('Service-account JSON must decode to an object.');
        }
        foreach (['client_email', 'private_key'] as $required) {
            if (!isset($payload[$required]) || !\is_string($payload[$required]) || '' === $payload[$required]) {
                throw new InvalidArgumentException(\sprintf('Service-account JSON is missing the "%s" field.', $required));
            }
        }

        return new self(
            $client,
            $payload['client_email'],
            $payload['private_key'],
            (string) ($payload['token_uri'] ?? self::DEFAULT_TOKEN_URI),
            $scope,
        );
    }

    public function getToken(): string
    {
        if (null !== $this->token && time() < $this->expiresAt) {
            return $this->token;
        }

        $now = time();
        $assertion = $this->buildAssertion($now);

        try {
            $response = $this->client->request('POST', $this->tokenUri, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'grant_type' => self::GRANT_TYPE,
                    'assertion' => $assertion,
                ]),
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Failed to reach the Google token endpoint.', 0, $e);
        }

        try {
            $payload = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new RuntimeException(\sprintf('The Google token endpoint returned a non-JSON response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        }

        if ($status >= 400) {
            $description = (string) ($payload['error_description'] ?? $payload['error'] ?? 'unknown error');
            throw new RuntimeException(\sprintf('Google token request failed (HTTP %d): "%s".', $status, $description));
        }

        if ($status >= 300) {
            throw new RuntimeException(\sprintf('Google token request failed (HTTP %d) for "%s".', $status, $response->getInfo('url')));
        }

        if (!isset($payload['access_token'], $payload['expires_in']) || !\is_string($payload['access_token']) || !is_numeric($payload['expires_in'])) {
            throw new RuntimeException('Google returned a malformed token response.');
        }

        $this->token = $payload['access_token'];
        $this->expiresAt = $now + (int) $payload['expires_in'] - self::EXPIRY_SAFETY_MARGIN_SECONDS;

        return $this->token;
    }

    private function buildAssertion(int $now): string
    {
        $header = Base64UrlSafe::encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], \JSON_THROW_ON_ERROR));
        $claims = Base64UrlSafe::encode(json_encode([
            'iss' => $this->clientEmail,
            'scope' => $this->scope,
            'aud' => $this->tokenUri,
            'iat' => $now - self::IAT_CLOCK_SKEW_SECONDS,
            'exp' => $now + 3600,
        ], \JSON_THROW_ON_ERROR));

        $signingInput = $header.'.'.$claims;

        $key = openssl_pkey_get_private($this->material());
        if (false === $key) {
            throw new InvalidArgumentException('The service-account private key is not a valid PEM-encoded RSA key.');
        }
        if (!openssl_sign($signingInput, $signature, $key, \OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to RS256-sign the JWT assertion: '.(openssl_error_string() ?: 'unknown error'));
        }

        return $signingInput.'.'.Base64UrlSafe::encode($signature);
    }
}
