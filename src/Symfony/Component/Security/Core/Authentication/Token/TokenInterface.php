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

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * TokenInterface is the interface for the user authentication information.
 *
 * @method string getUserIdentifier() returns the user identifier used during authentication (e.g. a user's email address or username)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface TokenInterface
{
    /**
     * Returns a string representation of the Token.
     *
     * This is only to be used for debugging purposes.
     */
    public function __toString(): string;

    /**
     * Returns the user roles.
     *
     * @return string[]
     */
    public function getRoleNames(): array;

    /**
     * Returns a user representation.
     *
     * @return UserInterface
     *
     * @see AbstractToken::setUser()
     */
    public function getUser();

    /**
     * Sets the authenticated user in the token.
     *
     * @throws \InvalidArgumentException
     */
    public function setUser(UserInterface $user);

    /**
     * Removes sensitive information from the token.
     */
    public function eraseCredentials();

    /**
     * @return array
     */
    public function getAttributes();

    /**
     * @param array $attributes The token attributes
     */
    public function setAttributes(array $attributes);

    /**
     * @return bool
     */
    public function hasAttribute(string $name);

    /**
     * @return mixed
     *
     * @throws \InvalidArgumentException When attribute doesn't exist for this token
     */
    public function getAttribute(string $name);

    public function setAttribute(string $name, mixed $value);

    /**
     * Returns all the necessary state of the object for serialization purposes.
     */
    public function __serialize(): array;

    /**
     * Restores the object state from an array given by __serialize().
     */
    public function __unserialize(array $data): void;
}
