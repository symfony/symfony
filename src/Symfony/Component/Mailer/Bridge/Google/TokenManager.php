<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Google;

use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Manages OAuth2 tokens for Google service accounts using JWT authentication.
 *
 * @author Pascal CESCON <pascal.cescon@gmail.com>
 */
final class TokenManager
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const GMAIL_SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    private ?string $token = null;
    private ?DatePoint $tokenExpires = null;

    /**
     * @param string $serviceAccountEmail The service account email address
     * @param string $privateKey          The RSA private key in PEM format
     * @param string $userEmail           The email address of the user to impersonate (domain-wide delegation)
     */
    public function __construct(
        private readonly string $serviceAccountEmail,
        #[\SensitiveParameter] private readonly string $privateKey,
        private readonly string $userEmail,
        private readonly HttpClientInterface $client,
    ) {
    }

    public function getToken(): string
    {
        // consider the token expired one minute early to absorb clock skew and request latency
        if (null !== $this->token && $this->tokenExpires > new DatePoint('+60 seconds')) {
            return $this->token;
        }

        $jwt = $this->createJwt();

        $response = $this->client->request('POST', self::TOKEN_ENDPOINT, [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the Google OAuth2 server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to authenticate with Google: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        }

        $tokenData = $response->toArray();

        if (!\is_string($tokenData['access_token'] ?? null) || !is_numeric($tokenData['expires_in'] ?? null)) {
            throw new HttpTransportException('The Google OAuth2 server response misses the "access_token" or "expires_in" key.', $response);
        }

        $this->token = $tokenData['access_token'];
        $this->tokenExpires = new DatePoint("+{$tokenData['expires_in']} seconds");

        return $this->token;
    }

    private function createJwt(): string
    {
        $now = (new DatePoint())->getTimestamp();

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $claims = [
            'iss' => $this->serviceAccountEmail,
            'sub' => $this->userEmail,
            'scope' => self::GMAIL_SEND_SCOPE,
            'aud' => self::TOKEN_ENDPOINT,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedClaims = $this->base64UrlEncode(json_encode($claims));

        $signatureInput = $encodedHeader.'.'.$encodedClaims;
        $signature = $this->sign($signatureInput);

        return $signatureInput.'.'.$this->base64UrlEncode($signature);
    }

    private function sign(string $data): string
    {
        $privateKey = openssl_pkey_get_private($this->privateKey);

        if (false === $privateKey) {
            throw new InvalidArgumentException('Invalid private key provided for Google service account authentication.');
        }

        if (!openssl_sign($data, $signature, $privateKey, \OPENSSL_ALGO_SHA256)) {
            throw new InvalidArgumentException('Failed to sign JWT for Google service account authentication.');
        }

        return $signature;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
