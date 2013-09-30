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
 * @since  2.4
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface TokenStorageInterface
{
    /**
     * Reads a stored CSRF token.
     *
     * @param string $tokenId The token ID
     * @param mixed  $default The value to be returned if no token is set
     *
     * @return mixed The stored token or the default value, if no token is set
     */
    public function getToken($tokenId, $default = null);

    /**
     * Stores a CSRF token.
     *
     * @param string $tokenId The token ID
     * @param mixed  $token   The CSRF token
     */
    public function setToken($tokenId, $token);

    /**
     * Checks whether a token with the given token ID exists.
     *
     * @param string $tokenId The token ID
     *
     * @return Boolean Returns true if a token is stored for the given token ID,
     *                 false otherwise.
     */
    public function hasToken($tokenId);
}
