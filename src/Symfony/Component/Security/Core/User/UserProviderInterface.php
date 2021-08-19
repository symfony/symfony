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

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Represents a class that loads UserInterface objects from some source for the authentication system.
 *
 * In a typical authentication configuration, a user identifier (e.g. a
 * username or email address) credential enters the system (via form login, or
 * any method). The user provider that is configured with that authentication
 * method is asked to load the UserInterface object for the given identifier (via
 * loadUserByIdentifier) so that the rest of the process can continue.
 *
 * Internally, a user provider can load users from any source (databases,
 * configuration, web service). This is totally independent of how the authentication
 * information is submitted or what the UserInterface object looks like.
 *
 * @see UserInterface
 *
 * @method UserInterface loadUserByIdentifier(string $identifier) loads the user for the given user identifier (e.g. username or email).
 *                                                                This method must throw UserNotFoundException if the user is not found.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface UserProviderInterface
{
    /**
     * Refreshes the user.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @return UserInterface
     *
     * @throws UnsupportedUserException if the user is not supported
     * @throws UserNotFoundException    if the user is not found
     */
    public function refreshUser(UserInterface $user);

    /**
     * Whether this provider supports the given user class.
     *
     * @return bool
     */
    public function supportsClass(string $class);

    /**
     * @return UserInterface
     *
     * @throws UserNotFoundException
     *
     * @deprecated since Symfony 5.3, use loadUserByIdentifier() instead
     */
    public function loadUserByUsername(string $username);
}
