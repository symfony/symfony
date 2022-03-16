<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Hasher;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Interface for the user password hasher service.
 *
 * @author Ariel Ferrandini <arielferrandini@gmail.com>
 */
interface UserPasswordHasherInterface
{
    /**
     * Hashes the plain password for the given user.
     */
    public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string;

    /**
     * Checks if the plaintext password matches the user's password.
     */
    public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool;

    /**
     * Checks if an encoded password would benefit from rehashing.
     */
    public function needsRehash(PasswordAuthenticatedUserInterface $user): bool;
}
