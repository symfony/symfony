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
 * Returns a bearer token usable against the Azure Key Vault REST API
 * (audience `https://vault.azure.net`).
 *
 * Implementations are expected to cache the token until it expires and to
 * refresh it transparently. {@see ClientCredentialsTokenProvider} ships the
 * common server-to-server case (tenant + clientId + clientSecret); deployments
 * that rely on Managed Identity, Workload Identity or any other Azure AD flow
 * provide their own implementation.
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
}
