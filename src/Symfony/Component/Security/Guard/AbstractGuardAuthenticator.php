<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

/**
 * An optional base class that creates a PostAuthenticationGuardToken for you.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
abstract class AbstractGuardAuthenticator implements AuthenticatorInterface
{
    /**
     * Default implementation of the AuthenticatorInterface::supports method
     * As we still have the deprecated GuardAuthenticatorInterface, this method must be implemented here
     * Once GuardAuthenticatorInterface will be removed, this method should be removed too.
     *
     * @param Request $request
     *
     * @return bool
     */
    public function supports(Request $request)
    {
        @trigger_error('The Symfony\Component\Security\Guard\AbstractGuardAuthenticator::supports default implementation is used. This is provided for backward compatibility on GuardAuthenticationInterface that is deprecated since version 3.1 and will be removed in 4.0. Provide your own implementation of the supports method instead.', E_USER_DEPRECATED);

        return true;
    }

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
