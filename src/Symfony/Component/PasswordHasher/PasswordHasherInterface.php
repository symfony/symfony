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
 * Provides password hashing capabilities.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface PasswordHasherInterface
{
    public const MAX_PASSWORD_LENGTH = 4096;

    /**
     * Hashes a plain password.
     *
     * @throws InvalidPasswordException When the plain password is invalid, e.g. excessively long
     */
    public function hash(#[\SensitiveParameter] string $plainPassword): string;

    /**
     * Verifies a plain password against a hash.
     */
    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool;

    /**
     * Checks if a password hash would benefit from rehashing.
     */
    public function needsRehash(string $hashedPassword): bool;
}
