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
 * Represents a class that loads UserInterface objects from some source for the authentication system.
 *
 * In a typical authentication configuration, a username (i.e. some unique
 * user identifier) credential enters the system (via form login, or any
 * method). The user provider that is configured with that authentication
 * method is asked to load the UserInterface object for the given username
 * (via loadUserByUsername) so that the rest of the process can continue.
 *
 * Internally, a user provider can load users from any source (databases,
 * configuration, web service). This is totally independent of how the authentication
 * information is submitted or what the UserInterface object looks like.
 *
 * @see UserInterface
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface UserProviderInterface extends BaseUserProviderInterface
{
    /**
     * Whether this provider supports the given user class
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class);
}
