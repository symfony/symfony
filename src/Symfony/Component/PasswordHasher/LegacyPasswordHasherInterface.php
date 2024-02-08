<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher;

use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;

/**
 * Provides password hashing and verification capabilities for "legacy" hashers that require external salts.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
interface LegacyPasswordHasherInterface extends PasswordHasherInterface
{
    /**
     * Hashes a plain password.
     *
     * @throws InvalidPasswordException If the plain password is invalid, e.g. excessively long
     */
    public function hash(#[\SensitiveParameter] string $plainPassword, ?string $salt = null): string;

    /**
     * Checks that a plain password and a salt match a password hash.
     */
    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword, ?string $salt = null): bool;
}
