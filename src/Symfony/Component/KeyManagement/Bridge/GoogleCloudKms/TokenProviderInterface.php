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
 * Returns an OAuth2 access token usable against the Google Cloud KMS REST API
 * (scope `https://www.googleapis.com/auth/cloudkms`).
 *
 * Implementations are expected to cache the token until it expires and to
 * refresh it transparently. {@see ServiceAccountTokenProvider} ships the
 * common `service_account` credentials flow (signed JWT exchanged for an
 * access token); deployments running on GCE/GKE/Cloud Run can implement this
 * interface against the metadata server instead.
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
