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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;

/**
 * Implement to throw AccountStatusException during the authentication process.
 *
 * Can be used when you want to check the account status, e.g when the account is
 * disabled or blocked. This should not be used to make authentication decisions.
 *
 * Implementations should be cheap, free of side effects, and should not assume that
 * the request is a login request: they run on every authentication, including each
 * "remember me" re-authentication, which happens on whatever URL the user comes
 * back to, and checkPostAuth() runs again on every user impersonation. Anything
 * that belongs to a login, such as recording a last-login date, belongs in a
 * listener on Symfony\Component\Security\Http\Event\LoginSuccessEvent instead: a
 * checker cannot tell a login from an impersonation. Throw an
 * AccountStatusException to reject the user; the firewall handles any
 * AuthenticationException the same way, but catches nothing else.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface UserCheckerInterface
{
    /**
     * Checks the user account before authentication.
     *
     * @throws AccountStatusException
     */
    public function checkPreAuth(UserInterface $user): void;

    /**
     * Checks the user account after authentication.
     *
     * @throws AccountStatusException
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void;
}
