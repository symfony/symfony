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
use Symfony\Component\Security\Http\Authenticator\FallbackUserLoader;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The token handler validates the token on the OIDC server and retrieves the user identifier.
 */
final class OidcUserInfoTokenHandler implements AccessTokenHandlerInterface, ResetInterface
{
    use OidcTrait;

    private ?OidcDiscovery $discovery = null;

    public function __construct(
        private HttpClientInterface $client,
        private ?LoggerInterface $logger = null,
        private string $claim = 'sub',
    ) {
    }

    public function enableDiscovery(CacheInterface $cache, string $oidcConfigurationCacheKey): void
    {
        // no TTL: the cache pool decides, as it did before this delegated to OidcDiscovery.
        // ".document" keeps the key clear of the entries previous releases wrote under the
        // bare one, in formats of their own: they expire with the pool, unread.
        $this->discovery = new OidcDiscovery($this->client, $cache, cacheTtl: null, cacheKey: $oidcConfigurationCacheKey.'.document', checkedEndpoints: ['userinfo_endpoint']);
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $userInfoEndpoint = '';

        if (null !== $this->discovery) {
            try {
                // Call OIDC discovery to retrieve userinfo endpoint
                // OIDC configuration is stored in cache
                $oidcConfiguration = $this->discovery->getConfiguration();

                // The scheme was checked against the URL that served the document before
                // the configuration was cached, so only the announcement is enforced here
                $userInfoEndpoint = self::checkDiscoveredEndpoint($oidcConfiguration['userinfo_endpoint'] ?? null, 'userinfo_endpoint', null);
            } catch (\Throwable $e) {
                $this->logger?->error('An error occurred while requesting OIDC configuration.', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
            }
        }

        try {
            // Call the OIDC server to retrieve the user info
            // If the token is invalid or expired, the OIDC server will return an error
            $claims = $this->client->request('GET', $userInfoEndpoint, [
                'auth_bearer' => $accessToken,
            ])->toArray();

            if (empty($claims[$this->claim])) {
                throw new MissingClaimException(\sprintf('"%s" claim not found on OIDC server response.', $this->claim));
            }

            // UserLoader argument can be overridden by a UserProvider on AccessTokenAuthenticator::authenticate
            return new UserBadge($claims[$this->claim], new FallbackUserLoader(function () use ($claims) {
                $claims['user_identifier'] = $claims[$this->claim];

                return $this->createUser($claims);
            }), $claims);
        } catch (\Exception $e) {
            $this->logger?->error('An error occurred on OIDC server.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }
    }

    public function reset(): void
    {
        $this->discovery?->reset();
    }
}
