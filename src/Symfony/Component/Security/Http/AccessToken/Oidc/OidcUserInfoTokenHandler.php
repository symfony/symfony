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
use Symfony\Component\Security\Http\AccessToken\Oidc\Exception\MissingClaimException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The token handler validates the token on the OIDC server and retrieves the user identifier.
 *
 * @experimental
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

            if (empty($claims[$this->claim])) {
                throw new MissingClaimException(sprintf('"%s" claim not found on OIDC server response.', $this->claim));
            }

            // UserLoader argument can be overridden by a UserProvider on AccessTokenAuthenticator::authenticate
            return new UserBadge($claims[$this->claim], fn () => $this->createUser($claims), $claims);
        } catch (\Throwable $e) {
            $this->logger?->error('An error occurred on OIDC server.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }
    }
}
