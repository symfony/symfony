<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AzureKeyVault;

use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Returns a bearer token usable against the Azure Key Vault REST API.
 *
 * The audience is `https://vault.azure.net`.
 *
 * Implementations are expected to cache the token until it expires or is invalidated, and to refresh it transparently.
 * {@see ClientCredentialsTokenProvider} ships the common server-to-server case (tenant + clientId + clientSecret).
 * Deployments that rely on Managed Identity, Workload Identity or any other Azure AD flow provide their own implementation.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface TokenProviderInterface
{
    /**
     * @throws RuntimeException When the token cannot be acquired
     */
    public function getToken(): string;

    /**
     * Discards the cached token if it matches the token rejected by the KMS.
     *
     * This only evicts a cached value; it must not revoke the token remotely or log it.
     * The next getToken() call acquires a new token unless another caller has already cached one.
     * Providers that do not cache can leave this method empty.
     * A cache shared between processes does not need to compare and delete atomically: the worst case is one extra token request.
     */
    public function invalidateToken(#[\SensitiveParameter] string $token): void;
}
