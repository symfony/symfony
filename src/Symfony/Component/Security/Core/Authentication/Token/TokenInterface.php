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
    public function __toString();

    /**
     * Returns the user roles.
     *
     * @return string[] The associated roles
     */
    public function getRoleNames(): array;

    /**
     * Returns the user credentials.
     *
     * @return mixed The user credentials
     */
    public function getCredentials();

    /**
     * Returns a user representation.
     *
     * @return string|object Can be a UserInterface instance, an object implementing a __toString method,
     *                       or the username as a regular string
     *
     * @see AbstractToken::setUser()
     */
    public function getUser();

    /**
     * Sets the user in the token.
     *
     * The user can be a UserInterface instance, or an object implementing
     * a __toString method or the username as a regular string.
     *
     * @param string|object $user The user
     *
     * @throws \InvalidArgumentException
     */
    public function setUser($user);

    /**
     * Returns the username.
     *
     * @return string
     */
    public function getUsername();

    /**
     * Returns whether the user is authenticated or not.
     *
     * @return bool true if the token has been authenticated, false otherwise
     */
    public function isAuthenticated();

    /**
     * Sets the authenticated flag.
     */
    public function setAuthenticated(bool $isAuthenticated);

    /**
     * Removes sensitive information from the token.
     */
    public function eraseCredentials();

    /**
     * Returns the token attributes.
     *
     * @return array The token attributes
     */
    public function getAttributes();

    /**
     * Sets the token attributes.
     *
     * @param array $attributes The token attributes
     */
    public function setAttributes(array $attributes);

    /**
     * Returns true if the attribute exists.
     *
     * @return bool true if the attribute exists, false otherwise
     */
    public function hasAttribute(string $name);

    /**
     * Returns an attribute value.
     *
     * @return mixed The attribute value
     *
     * @throws \InvalidArgumentException When attribute doesn't exist for this token
     */
    public function getAttribute(string $name);

    /**
     * Sets an attribute.
     *
     * @param mixed $value The attribute value
     */
    public function setAttribute(string $name, $value);

    /**
     * Returns all the necessary state of the object for serialization purposes.
     */
    public function __serialize(): array;

    /**
     * Restores the object state from an array given by __serialize().
     */
    public function __unserialize(array $data): void;
}
