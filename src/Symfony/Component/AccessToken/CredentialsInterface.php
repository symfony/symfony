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
 * Represent access token credentials.
 *
 * Side concepts such as client identifier, client secret, scope and such
 * you may find in standards such as OAuth flows are implementation details.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
interface CredentialsInterface
{
    /**
     * Get a unique and reproducible identifier for this credentials.
     *
     * This will be used as a part for lock and cache identifiers, it must be
     * as unique as possible, and reproducible, no randomness is allowed in
     * order to avoid cache pollution.
     */
    public function getId(): string;

    /**
     * Get default lifetime for this credentials.
     *
     * When the remote service does not give any information about token
     * lifetime, the value here will be used.
     */
    public function getDefaultLifetime(): int;
}
