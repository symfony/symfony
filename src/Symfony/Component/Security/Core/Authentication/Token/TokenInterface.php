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

use Symfony\Component\Security\Core\Role\RoleInterface;

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
     *
     * @since v2.1.0
     */
    public function __toString();

    /**
     * Returns the user roles.
     *
     * @return RoleInterface[] An array of RoleInterface instances.
     *
     * @since v2.1.0
     */
    public function getRoles();

    /**
     * Returns the user credentials.
     *
     * @return mixed The user credentials
     *
     * @since v2.1.0
     */
    public function getCredentials();

    /**
     * Returns a user representation.
     *
     * @return mixed either returns an object which implements __toString(), or
     *                  a primitive string is returned.
     *
     * @since v2.1.0
     */
    public function getUser();

    /**
     * Sets a user.
     *
     * @param mixed $user
     *
     * @since v2.1.0
     */
    public function setUser($user);

    /**
     * Returns the username.
     *
     * @return string
     *
     * @since v2.1.0
     */
    public function getUsername();

    /**
     * Returns whether the user is authenticated or not.
     *
     * @return Boolean true if the token has been authenticated, false otherwise
     *
     * @since v2.1.0
     */
    public function isAuthenticated();

    /**
     * Sets the authenticated flag.
     *
     * @param Boolean $isAuthenticated The authenticated flag
     *
     * @since v2.1.0
     */
    public function setAuthenticated($isAuthenticated);

    /**
     * Removes sensitive information from the token.
     *
     * @since v2.1.0
     */
    public function eraseCredentials();

    /**
     * Returns the token attributes.
     *
     * @return array The token attributes
     *
     * @since v2.1.0
     */
    public function getAttributes();

    /**
     * Sets the token attributes.
     *
     * @param array $attributes The token attributes
     *
     * @since v2.1.0
     */
    public function setAttributes(array $attributes);

    /**
     * Returns true if the attribute exists.
     *
     * @param string $name The attribute name
     *
     * @return Boolean true if the attribute exists, false otherwise
     *
     * @since v2.1.0
     */
    public function hasAttribute($name);

    /**
     * Returns an attribute value.
     *
     * @param string $name The attribute name
     *
     * @return mixed The attribute value
     *
     * @throws \InvalidArgumentException When attribute doesn't exist for this token
     *
     * @since v2.1.0
     */
    public function getAttribute($name);

    /**
     * Sets an attribute.
     *
     * @param string $name  The attribute name
     * @param mixed  $value The attribute value
     *
     * @since v2.1.0
     */
    public function setAttribute($name, $value);
}
