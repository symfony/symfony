<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\GoogleCloudKms;

use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Returns an OAuth2 access token usable against the Google Cloud KMS REST API.
 *
 * The scope is `https://www.googleapis.com/auth/cloudkms`.
 *
 * Implementations are expected to cache the token until it expires or is invalidated, and to refresh it transparently.
 * {@see ServiceAccountTokenProvider} ships the common `service_account` credentials flow (signed JWT exchanged for an access token).
 * Deployments running on GCE/GKE/Cloud Run can implement this interface against the metadata server instead.
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
