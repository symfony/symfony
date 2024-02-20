<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class AccessTokenFetcher
{
    public function __construct(
        private readonly AccessTokenManagerInterface $accessTokenManager,
        private readonly CredentialsInterface $credentials,
    ) {}

    /**
     * Create instance using given URI
     */
    public static function createWithUri(AccessTokenManagerInterface $accessTokenManager, #[\SensitiveParameter] string $uri): self
    {
        return new self($accessTokenManager, $accessTokenManager->createCredentials($uri));
    }

    /**
     * Create credentials interface with given URI.
     */
    public static function createCredentialsWithUri(AccessTokenManagerInterface $accessTokenManager, #[\SensitiveParameter] string $uri): CredentialsInterface
    {
        return $accessTokenManager->createCredentials($uri);
    }

    /**
     * Get access token.
     */
    public function getAccessToken(): AccessTokenInterface
    {
        return $this->accessTokenManager->getAccessToken($this->credentials);
    }

    /**
     * Force refresh access token, delete current one if exists.
     */
    public function refreshAccessToken(): AccessTokenInterface
    {
        return $this->accessTokenManager->refreshAccessToken($this->credentials);
    }

    /**
     * Delete existing access token.
     */
    public function deleteAccessToken(): void
    {
        $this->accessTokenManager->deleteAccessToken($this->credentials);
    }
}
