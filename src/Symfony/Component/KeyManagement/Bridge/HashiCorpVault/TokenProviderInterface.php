<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\HashiCorpVault;

use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Returns a Vault token usable against the Transit secret engine.
 *
 * Implementations are expected to cache the token until it expires or is invalidated, and to refresh it transparently.
 * {@see StaticTokenProvider} wraps a token obtained out of band, like the one of the DSN.
 * {@see KubernetesAuthTokenProvider} logs in with the pod's service-account token.
 * Deployments that rely on another auth method provide their own implementation.
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
     * Discards the cached token if it matches the token rejected by Vault.
     *
     * This only evicts a cached value; it must not revoke the token remotely or log it.
     * The next getToken() call acquires a new token unless another caller has already cached one.
     * Providers that do not cache can leave this method empty.
     * A cache shared between processes does not need to compare and delete atomically: the worst case is one extra login.
     */
    public function invalidateToken(#[\SensitiveParameter] string $token): void;
}
