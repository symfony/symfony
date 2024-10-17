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
 * Access token provider fetches access tokens from remote services or local configuration.
 * It is unaware of cache and will hit directly the remote service.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
interface ProviderInterface
{
    /**
     * Does this provider supports the given credentials.
     */
    public function supports(CredentialsInterface $credentials): bool;

    /**
     * Fetch a valid access token for credentials.
     */
    public function getAccessToken(CredentialsInterface $credentials): AccessTokenInterface;

    /**
     * Same as getAccessToken() but attempt a refresh if the provider supports it.
     *
     * If provider doesn't support refreshing, force fetch a new token.
     */
    public function refreshAccessToken(CredentialsInterface $credentials): AccessTokenInterface;
}
