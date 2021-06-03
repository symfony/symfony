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
 * Represents the interface that all user classes must implement.
 *
 * This interface is useful because the authentication layer can deal with
 * the object through its lifecycle, using the object to get the hashed
 * password (for checking against a submitted password), assigning roles
 * and so on.
 *
 * Regardless of how your users are loaded or where they come from (a database,
 * configuration, web service, etc.), you will have a class that implements
 * this interface. Objects that implement this interface are created and
 * loaded by different objects that implement UserProviderInterface.
 *
 * @see UserProviderInterface
 *
 * @method string getUserIdentifier() returns the identifier for this user (e.g. its username or e-mailaddress)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface UserInterface
{
    /**
     * Returns the roles granted to the user.
     *
     *     public function getRoles()
     *     {
     *         return ['ROLE_USER'];
     *     }
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return string[] The user roles
     */
    public function getRoles();

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the hashed password. On authentication, a plain-text
     * password will be hashed, and then compared to this value.
     *
     * This method is deprecated since Symfony 5.3, implement it from {@link PasswordAuthenticatedUserInterface} instead.
     *
     * @return string|null The hashed password if any
     */
    public function getPassword();

    /**
     * Returns the salt that was originally used to hash the password.
     *
     * This can return null if the password was not hashed using a salt.
     *
     * This method is deprecated since Symfony 5.3, implement it from {@link LegacyPasswordAuthenticatedUserInterface} instead.
     *
     * @return string|null The salt
     */
    public function getSalt();

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials();

    /**
     * @return string
     *
     * @deprecated since Symfony 5.3, use getUserIdentifier() instead
     */
    public function getUsername();
}
