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
 * UserInterface is the interface that user classes must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface UserInterface
{
    /**
     * Returns the roles granted to the user.
     *
     * @return Role[] The user roles
     */
    function getRoles();

    /**
     * Returns the password used to authenticate the user.
     *
     * @return string The password
     */
    function getPassword();

    /**
     * Returns the salt.
     *
     * @return string The salt
     */
    function getSalt();

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    function getUsername();

    /**
     * Removes sensitive data from the user.
     *
     * @return void
     */
    function eraseCredentials();

    /**
     * The equality comparison should neither be done by referential equality
     * nor by comparing identities (i.e. getId() === getId()).
     *
     * However, you do not need to compare every attribute, but only those that
     * are relevant for assessing whether re-authentication is required.
     *
     * @param UserInterface $user
     *
     * @return Boolean
     */
    function equals(UserInterface $user);
}
