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
     * {@inheritdoc}
     */
    public function supports(Request $request)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0. Implement the "%s::supports()" method in class "%s" instead.', __METHOD__, AuthenticatorInterface::class, \get_class($this)), E_USER_DEPRECATED);

        return true;
    }

    /**
     * Shortcut to create a PostAuthenticationGuardToken for you, if you don't really
     * care about which authenticated token you're using.
     *
     * @param string $providerKey
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
