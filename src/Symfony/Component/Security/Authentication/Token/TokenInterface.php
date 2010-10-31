<?php

namespace Symfony\Component\Security\Authentication\Token;

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
     * Returns the user credentials.
     *
     * @return mixed The user credentials
     */
    function getCredentials();

    /**
     * Checks whether the token is immutable or not.
     *
     * @return Boolean true if the token is immutable, false otherwise
     */
    function isImmutable();

    /**
     * Returns a user instance.
     *
     * @return object The User instance
     */
    function getUser();

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
     * Removes sensitive information from the token.
     */
    function eraseCredentials();
}
