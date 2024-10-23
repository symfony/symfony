<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Manager;

use Symfony\Component\AccessToken\AccessTokenInterface;
use Symfony\Component\AccessToken\AccessTokenManagerInterface;
use Symfony\Component\AccessToken\CredentialsInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Cache tokens.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class CacheAccessTokenManagerDecorator implements AccessTokenManagerInterface
{
    public function __construct(
        private readonly AccessTokenManagerInterface $decorated,
        private readonly CacheInterface $cache,
    ) {}

    public function createCredentials(string $uri): CredentialsInterface
    {
        return $this->decorated->createCredentials($uri);
    }

    public function getAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        $id = $this->getCacheKey($credentials);

        return $this->cache->get(
            $id,
            function (ItemInterface $item) use ($credentials) {
                $accessToken = $this->decorated->getAccessToken($credentials);
                $item->expiresAt($accessToken->getExpiresAt());

                return $accessToken;
            }
        );
    }

    public function refreshAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        $id = $this->getCacheKey($credentials);

        $this->cache->delete($id); // Should be useless thanks to INF below.

        return $this->cache->get(
            $id,
            function (ItemInterface $item) use ($credentials) {
                $accessToken = $this->decorated->refreshAccessToken($credentials);
                $item->expiresAt($accessToken->getExpiresAt());

                return $accessToken;
            },
            \INF,  // As per documentation, force immediate expiration (recompute value).
        );
    }

    public function deleteAccessToken(CredentialsInterface $credentials): void
    {
        $id = $this->getCacheKey($credentials);
        $this->cache->delete($id);
        $this->decorated->deleteAccessToken($credentials);
    }

    /**
     * Compute cache key.
     */
    protected function getCacheKey(CredentialsInterface $credentials): string
    {
        return 'actk-' . $credentials->getId();
    }
}
