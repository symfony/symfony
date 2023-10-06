<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\Oidc;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\FallbackUserLoader;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The token handler validates the token on the OIDC server and retrieves the user identifier.
 */
final class OidcUserInfoTokenHandler implements AccessTokenHandlerInterface
{
    use OidcTrait;

    public function __construct(
        private HttpClientInterface $client,
        private ?LoggerInterface $logger = null,
        private string $claim = 'sub'
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        try {
            // Call the OIDC server to retrieve the user info
            // If the token is invalid or expired, the OIDC server will return an error
            $claims = $this->client->request('GET', '', [
                'auth_bearer' => $accessToken,
            ])->toArray();
        } catch (HttpExceptionInterface $e) {
            $this->logger?->debug('An error occurred while trying to access the UserInfo endpoint on the OIDC server.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }

        if (!($claims[$this->claim] ?? false)) {
            throw new BadCredentialsException(sprintf('The "%s" claim is missing from the OIDC UserInfo response.', $this->claim));
        }

        // UserLoader argument can be overridden by a UserProvider on AccessTokenAuthenticator::authenticate
        return new UserBadge($claims[$this->claim], new FallbackUserLoader(fn () => $this->createUser($claims)), $claims);
    }
}
