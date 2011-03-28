<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * TokenInterface is the interface for the user authentication information.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface TokenInterface extends \Serializable
{
    /**
     * Returns a string representation of the Token.
     *
     * This is only to be used for debugging purposes.
     *
     * @return string
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
     * Returns a user representation.
     *
     * @return mixed either returns an object which implements __toString(), or
     *                  a primitive string is returned.
     */
    function getUser();

    /**
     * Sets a user.
     *
     * @param mixed $user
     */
    function setUser($user);

    /**
     * Returns the username.
     *
     * @return string
     */
    function getUsername();

    /**
     * Returns whether the user is authenticated or not.
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

    /**
     * Returns the token attributes.
     *
     * @return array The token attributes
     */
    function getAttributes();

    /**
     * Sets the token attributes.
     *
     * @param array $attributes The token attributes
     */
    function setAttributes(array $attributes);

    /**
     * Returns true if the attribute exists.
     *
     * @param  string  $name  The attribute name
     *
     * @return Boolean true if the attribute exists, false otherwise
     */
    function hasAttribute($name);

    /**
     * Returns an attribute value.
     *
     * @param string $name The attribute name
     *
     * @return mixed The attribute value
     *
     * @throws \InvalidArgumentException When attribute doesn't exist for this token
     */
    function getAttribute($name);

    /**
     * Sets an attribute.
     *
     * @param string $name  The attribute name
     * @param mixed  $value The attribute value
     */
    function setAttribute($name, $value);
}
