<?php

namespace Symfony\Component\Security\Guard;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\GenericGuardToken;

/**
 * An optional base class that creates a GenericGuardToken for you
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 */
abstract class AbstractGuardAuthenticator implements GuardAuthenticatorInterface
{
    /**
     * Shortcut to create a GenericGuardToken for you, if you don't really
     * care about which authenticated token you're using
     *
     * @param UserInterface $user
     * @param string $providerKey
     * @return GenericGuardToken
     */
    public function createAuthenticatedToken(UserInterface $user, $providerKey)
    {
        return new GenericGuardToken(
            $user,
            $providerKey,
            $user->getRoles()
        );
    }
}