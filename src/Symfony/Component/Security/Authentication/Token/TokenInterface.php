<?php

namespace Symfony\Component\Security\Authentication\Token;

use Symfony\Component\Security\User\AccountInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * TokenInterface is the interface for the user authentication information.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface TokenInterface extends \Serializable
{
    /**
     * Returns a string representation of the token.
     *
     * @return string A string representation
     */
    function __toString();

    /**
     * Returns the user roles.
     *
     * @return Role[] An array of Role instances.
     */
    function getRoles();

    /**
     * Sets the user's roles
     *
     * @param array $roles
     * @return void
     */
    function setRoles(array $roles);

    /**
     * Returns the user credentials.
     *
     * @return mixed The user credentials
     */
    function getCredentials();

    /**
     * Returns a user representation.
     *
     * @return mixed either returns an object which implements __toString(), or
     *                  a primitive string is returned.
     */
    function getUser();

    /**
     * Sets the user.
     *
     * @param mixed $user can either be an object which implements __toString(), or
     *                       only a primitive string
     */
    function setUser($user);

    /**
     * Returns a unique id for the user provider that was used to retrieve the user
     *
     * @return string
     */
    function getUserProviderName();

    /**
     * Checks if the user is authenticated or not.
     *
     * @return Boolean true if the token has been authenticated, false otherwise
     */
    function isAuthenticated();

    /**
     * Sets the authenticated flag.
     *
     * @param Boolean $isAuthenticated The authenticated flag
     */
    function setAuthenticated($isAuthenticated);

    /**
     * Whether this token is considered immutable
     *
     * @return Boolean
     */
    function isImmutable();

    /**
     * Marks this token as immutable. This change cannot be reversed.
     *
     * You'll need to create a new token if you want a mutable token again.
     *
     * @return void
     */
    function setImmutable();

    /**
     * Removes sensitive information from the token.
     */
    function eraseCredentials();
}
