<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

/**
 * For users that can be authenticated using a password.
 *
 * The __serialize/__unserialize() magic methods can be implemented on the user
 * class to prevent hashed passwords from being put in the session storage.
 * If the password is not stored at all in the session, getPassword() should
 * return null after unserialization, and then, changing the user's password
 * won't invalidate its sessions.
 * In order to invalidate the user sessions while not storing the password hash
 * in the session, it's also possible to hash the password hash before
 * serializing it; crc32c is the only algorithm supported.
 * For example:
 *
 *     public function __serialize(): array
 *     {
 *         $data = (array) $this;
 *         $data["\0".self::class."\0password"] = hash('crc32c', $this->password);
 *
 *         return $data;
 *     }
 *
 * Implement EquatableInterface if you need another logic.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface PasswordAuthenticatedUserInterface
{
    /**
     * Returns the hashed password used to authenticate the user.
     *
     * Usually on authentication, a plain-text password will be compared to this value.
     */
    public function getPassword(): ?string;
}
