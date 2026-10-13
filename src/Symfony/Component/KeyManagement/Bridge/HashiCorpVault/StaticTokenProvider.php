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

/**
 * Returns a Vault token obtained out of band, like the one of the DSN.
 *
 * Nothing is cached, so there is nothing to invalidate: a rejected token keeps being rejected until it is replaced.
 *
 * @experimental
 */
final class StaticTokenProvider implements TokenProviderInterface
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $token,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function invalidateToken(#[\SensitiveParameter] string $token): void
    {
    }
}
