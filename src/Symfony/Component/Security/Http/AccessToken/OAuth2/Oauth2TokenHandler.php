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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OAuth2User;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\String\u;

/**
 * The token handler validates the token on the authorization server and the Introspection Endpoint.
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
            ])->toArray();

            $sub = $claims['sub'] ?? null;
            $username = $claims['username'] ?? null;
            if (!$sub && !$username) {
                throw new BadCredentialsException('"sub" and "username" claims not found on the authorization server response. At least one is required.');
            }
            $active = $claims['active'] ?? false;
            if (!$active) {
                throw new BadCredentialsException('The claim "active" was not found on the authorization server response or is set to false.');
            }

            return new UserBadge($sub ?? $username, fn () => $this->createUser($claims), $claims);
        } catch (AuthenticationException $e) {
            $this->logger?->error('An error occurred on the authorization server.', [
                'error' => $e->getMessage(),
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

        if ('' !== ($claims['emailVerified'] ?? '')) {
            $claims['emailVerified'] = (bool) $claims['emailVerified'];
        }

        if ('' !== ($claims['phoneNumberVerified'] ?? '')) {
            $claims['phoneNumberVerified'] = (bool) $claims['phoneNumberVerified'];
        }

        return new OAuth2User(...$claims);
    }
}
