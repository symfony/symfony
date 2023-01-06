<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenStorage;

/**
 * Stores CSRF tokens.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface TokenStorageInterface
{
    /**
     * Reads a stored CSRF token.
     *
     * @throws \Symfony\Component\Security\Csrf\Exception\TokenNotFoundException If the token ID does not exist
     */
    public function getToken(string $tokenId): string;

    /**
     * Stores a CSRF token.
     */
    public function setToken(string $tokenId, #[\SensitiveParameter] string $token);

    /**
     * Removes a CSRF token.
     *
     * @return string|null Returns the removed token if one existed, NULL
     *                     otherwise
     */
    public function removeToken(string $tokenId): ?string;

    /**
     * Checks whether a token with the given token ID exists.
     */
    public function hasToken(string $tokenId): bool;
}
