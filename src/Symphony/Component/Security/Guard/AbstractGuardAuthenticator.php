<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Guard;

use Symphony\Component\Security\Core\User\UserInterface;
use Symphony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

/**
 * An optional base class that creates a PostAuthenticationGuardToken for you.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
abstract class AbstractGuardAuthenticator implements AuthenticatorInterface
{
    /**
     * Shortcut to create a PostAuthenticationGuardToken for you, if you don't really
     * care about which authenticated token you're using.
     *
     * @param UserInterface $user
     * @param string        $providerKey
     *
     * @return PostAuthenticationGuardToken
     */
    public function createAuthenticatedToken(UserInterface $user, $providerKey)
    {
        return new PostAuthenticationGuardToken(
            $user,
            $providerKey,
            $user->getRoles()
        );
    }
}
