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
 *
 * @method string hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword)    Hashes the plain password for the given user.
 * @method bool   isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword) Checks if the plaintext password matches the user's password.
 * @method bool   needsRehash(PasswordAuthenticatedUserInterface $user)                            Checks if an encoded password would benefit from rehashing.
 */
interface UserPasswordHasherInterface
{
}
