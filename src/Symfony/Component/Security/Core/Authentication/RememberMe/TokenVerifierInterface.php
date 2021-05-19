<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\RememberMe;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface TokenVerifierInterface
{
    /**
     * Verifies that the given $token is valid.
     *
     * This lets you override the token check logic to for example accept slightly outdated tokens.
     *
     * Do not forget to implement token comparisons using hash_equals for a secure implementation.
     */
    public function verifyToken(PersistentTokenInterface $token, string $tokenValue): bool;

    /**
     * Updates an existing token with a new token value and lastUsed time.
     */
    public function updateExistingToken(PersistentTokenInterface $token, string $tokenValue, \DateTimeInterface $lastUsed): void;
}
