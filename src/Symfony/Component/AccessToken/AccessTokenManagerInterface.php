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

use Symfony\Component\AccessToken\Exception\FactoryNotFoundException;
use Symfony\Component\AccessToken\Exception\ProviderNotFoundException;

/**
 * Entry point for this component. Users should always use this interface.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
interface AccessTokenManagerInterface
{
    /**
     * Create credentials from URI.
     *
     * @throws FactoryNotFoundException in case the credentials factory is not found
     */
    public function createCredentials(string $uri): CredentialsInterface;

    /**
     * Get access token for credentials.
     *
     * @throws ProviderNotFoundException in case no provider match these credentials
     */
    public function getAccessToken(CredentialsInterface $credentials): AccessTokenInterface;

    /**
     * Force refresh access token for credentials, delete current one if exists.
     *
     * @throws ProviderNotFoundException in case no provider match these credentials
     */
    public function refreshAccessToken(CredentialsInterface $credentials): AccessTokenInterface;

    /**
     * Delete existing access token for credentials.
     */
    public function deleteAccessToken(CredentialsInterface $credentials): void;
}
