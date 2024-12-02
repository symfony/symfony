<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class AccessTokenFetcher
{
    public function __construct(
        private readonly AccessTokenManagerInterface $accessTokenManager,
        private readonly CredentialsInterface $credentials,
    ) {
    }

    /**
     * Get access token using the user configured credentials.
     *
     * If a valid cached version exists, use it, otherwise fetch it from the remote service.
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
