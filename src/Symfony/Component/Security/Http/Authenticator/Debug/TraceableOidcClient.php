<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Debug;

use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Records the calls made to the OIDC provider, for the profiler.
 *
 * A token endpoint response is never kept as it is: its tokens are described by
 * {@see OidcTokenDescriber}, and only its non-credential fields are kept. A failure
 * keeps the class and message of the exception, and the RFC 6749, Section 5.2 error
 * fields of the response when there is one, since the profiler no longer holds the
 * body of a token endpoint response.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class TraceableOidcClient implements OidcClientInterface, ResetInterface
{
    private const RESPONSE_FIELDS = ['token_type', 'expires_in', 'scope'];
    private const TOKEN_FIELDS = ['id_token', 'access_token', 'refresh_token'];
    private const ERROR_FIELDS = ['error', 'error_description', 'error_uri'];
    private const MAX_ERROR_LENGTH = 500;

    /**
     * @var list<array{operation: string, duration: float, request: array<string, mixed>, response: ?array<string, mixed>, error: ?array<string, mixed>}>
     */
    private array $calls = [];

    public function __construct(
        private readonly OidcClientInterface $client,
    ) {
    }

    public function exchangeCode(#[\SensitiveParameter] string $code, string $redirectUri, #[\SensitiveParameter] ?string $codeVerifier = null): array
    {
        return $this->trace(
            'authorization_code',
            ['redirect_uri' => $redirectUri, 'code_verifier' => null !== $codeVerifier, 'client_authentication' => $this->client->getClientAuthenticationMethod()],
            fn (): array => $this->client->exchangeCode($code, $redirectUri, $codeVerifier),
            $this->describeTokenResponse(...),
        );
    }

    public function refreshToken(#[\SensitiveParameter] string $refreshToken, array $scopes = []): array
    {
        return $this->trace(
            'refresh_token',
            ['scopes' => $scopes, 'client_authentication' => $this->client->getClientAuthenticationMethod()],
            fn (): array => $this->client->refreshToken($refreshToken, $scopes),
            function (array $response) use ($refreshToken): array {
                $description = $this->describeTokenResponse($response);
                // RFC 6749, Section 6: issuing a new refresh token is a MAY
                $description['refresh_token_rotated'] = null !== $description['refresh_token'] && $response['refresh_token'] !== $refreshToken;

                return $description;
            },
        );
    }

    public function fetchUserInfo(#[\SensitiveParameter] string $accessToken): array
    {
        return $this->trace(
            'userinfo',
            [],
            fn (): array => $this->client->fetchUserInfo($accessToken),
            static fn (array $claims): array => ['claims' => $claims],
        );
    }

    public function getClientAuthenticationMethod(): string
    {
        return $this->client->getClientAuthenticationMethod();
    }

    /**
     * @return list<array{operation: string, duration: float, request: array<string, mixed>, response: ?array<string, mixed>, error: ?array<string, mixed>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function reset(): void
    {
        $this->calls = [];
    }

    /**
     * @param array<string, mixed>   $request  What the request was made with, credentials excluded
     * @param \Closure(): array      $call     The call to the decorated client
     * @param \Closure(array): array $describe Turns the response into what the profiler may keep
     *
     * @return array<string, mixed> The response of the decorated client, untouched
     */
    private function trace(string $operation, array $request, \Closure $call, \Closure $describe): array
    {
        $record = ['operation' => $operation, 'duration' => 0.0, 'request' => $request, 'response' => null, 'error' => null];
        $start = microtime(true);

        try {
            $response = $call();
            $record['response'] = $describe($response);

            return $response;
        } catch (\Throwable $e) {
            $record['error'] = $this->describeError($e);

            throw $e;
        } finally {
            $record['duration'] = microtime(true) - $start;
            $this->calls[] = $record;
        }
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function describeTokenResponse(array $response): array
    {
        $description = [];
        foreach (self::RESPONSE_FIELDS as $field) {
            $description[$field] = \is_scalar($response[$field] ?? null) ? $response[$field] : null;
        }
        foreach (self::TOKEN_FIELDS as $field) {
            $description[$field] = \is_string($response[$field] ?? null) && '' !== $response[$field] ? OidcTokenDescriber::describe($response[$field]) : null;
        }
        // the names of the other fields tell what the provider adds, their values could hold anything
        $description['other_fields'] = array_values(array_map(strval(...), array_diff(array_keys($response), self::RESPONSE_FIELDS, self::TOKEN_FIELDS)));

        return $description;
    }

    /**
     * @return array<string, mixed>
     */
    private function describeError(\Throwable $e): array
    {
        $error = ['class' => $e::class, 'message' => $e->getMessage(), 'status_code' => null];
        foreach (self::ERROR_FIELDS as $field) {
            $error[$field] = null;
        }

        for ($cause = $e; null !== $cause; $cause = $cause->getPrevious()) {
            if (!$cause instanceof HttpExceptionInterface) {
                continue;
            }

            try {
                $response = $cause->getResponse();
                $error['status_code'] = $response->getStatusCode();
                // RFC 6749, Section 5.2: the error response is a JSON object, but a proxy
                // or a WAF may answer with anything
                $body = $response->toArray(false);
                foreach (self::ERROR_FIELDS as $field) {
                    if (\is_string($body[$field] ?? null)) {
                        $error[$field] = substr($body[$field], 0, self::MAX_ERROR_LENGTH);
                    }
                }
            } catch (\Throwable) {
                // the response is not readable: the class and message are all there is
            }

            break;
        }

        return $error;
    }
}
