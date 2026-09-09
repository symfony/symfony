<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\OAuth2;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OAuth2User;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\String\u;

/**
 * The token handler validates the token on the authorization server and the Introspection Endpoint.
 *
 * Anything the introspection request throws is an answer the resource server could not read, so it
 * turns into the bad credentials the firewall reports as a 401: a server that is unreachable, that
 * refuses the caller, or that answers something other than the JSON object RFC 7662 §2.2 defines
 * says nothing about the token, and never that it is usable.
 *
 * @see https://tools.ietf.org/html/rfc7662
 *
 * @internal
 */
final class Oauth2TokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * RFC 7662 §2.2 defines "active" as a boolean, and the introspection response is JSON, so the
     * member is compared to true: nothing else states that the token can be used, the string
     * "false" an authorization server may answer with least of all.
     */
    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        try {
            // Call the Authorization server to retrieve the resource owner details
            // If the token is invalid or expired, the Authorization server will return an error
            $claims = $this->client->request('POST', '', [
                'body' => [
                    'token' => $accessToken,
                    'token_type_hint' => 'access_token',
                ],
                'max_redirects' => 0,
            ])->toArray();

            $sub = $claims['sub'] ?? null;
            $username = $claims['username'] ?? null;
            if (!$sub && !$username) {
                throw new BadCredentialsException('"sub" and "username" claims not found on the authorization server response. At least one is required.');
            }
            if (true !== ($claims['active'] ?? false)) {
                throw new BadCredentialsException('The claim "active" was not found on the authorization server response or is set to false.');
            }

            return new UserBadge($sub ?? $username, fn () => $this->createUser($claims), $claims);
        } catch (\Exception $e) {
            $this->logger?->error('An error occurred on the authorization server.', [
                'error' => $e->getMessage(),
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }
    }

    private function createUser(array $claims): OAuth2User
    {
        if (!\function_exists(\Symfony\Component\String\u::class)) {
            throw new \LogicException('You cannot use the "OAuth2TokenHandler" since the String component is not installed. Try running "composer require symfony/string".');
        }

        foreach ($claims as $claim => $value) {
            unset($claims[$claim]);
            if ('' === $value || null === $value) {
                continue;
            }
            $claims[u($claim)->camel()->toString()] = $value;
        }

        if ('' !== ($claims['updatedAt'] ?? '')) {
            $claims['updatedAt'] = (new \DateTimeImmutable())->setTimestamp($claims['updatedAt']);
        }

        // a string "false" would cast to true, so only a recognizable boolean is kept,
        // and any other value is dropped rather than turned into a verified flag
        foreach (['emailVerified', 'phoneNumberVerified'] as $flag) {
            if (isset($claims[$flag]) && '' !== $claims[$flag] && null === $claims[$flag] = filter_var($claims[$flag], \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE)) {
                unset($claims[$flag]);
            }
        }

        return new OAuth2User(...$claims);
    }
}
