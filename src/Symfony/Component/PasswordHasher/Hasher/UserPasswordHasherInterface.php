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

use Symfony\Component\Security\Core\User\UserInterface;

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
    public function hashPassword(UserInterface $user, string $plainPassword): string;

    /**
     * Checks if the plaintext password matches the user's password.
     */
    public function isPasswordValid(UserInterface $user, string $plainPassword): bool;

    /**
     * Checks if a password hash would benefit from rehashing.
     */
    public function needsRehash(UserInterface $user): bool;
}
