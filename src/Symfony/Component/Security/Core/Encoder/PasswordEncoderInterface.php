<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * PasswordEncoderInterface is the interface for all encoders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface PasswordEncoderInterface
{
    /**
     * Encodes the raw password.
     *
     * @return string The encoded password
     *
     * @throws BadCredentialsException   If the raw password is invalid, e.g. excessively long
     * @throws \InvalidArgumentException If the salt is invalid
     */
    public function encodePassword(string $raw, ?string $salt);

    /**
     * Checks a raw password against an encoded password.
     *
     * @param string      $encoded An encoded password
     * @param string      $raw     A raw password
     * @param string|null $salt    The salt
     *
     * @return bool true if the password is valid, false otherwise
     *
     * @throws \InvalidArgumentException If the salt is invalid
     */
    public function isPasswordValid(string $encoded, string $raw, ?string $salt);

    /**
     * Checks if an encoded password would benefit from rehashing.
     */
    public function needsRehash(string $encoded): bool;
}
