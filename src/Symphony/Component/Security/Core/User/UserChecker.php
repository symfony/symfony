<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\User;

use Symphony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symphony\Component\Security\Core\Exception\LockedException;
use Symphony\Component\Security\Core\Exception\DisabledException;
use Symphony\Component\Security\Core\Exception\AccountExpiredException;

/**
 * UserChecker checks the user account flags.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof AdvancedUserInterface && !$user instanceof User) {
            return;
        }

        if ($user instanceof AdvancedUserInterface && !$user instanceof User) {
            @trigger_error(sprintf('Calling %s with an AdvancedUserInterface is deprecated since Symphony 4.1. Create a custom user checker if you wish to keep this functionality.', __METHOD__), E_USER_DEPRECATED);
        }

        if (!$user->isAccountNonLocked()) {
            $ex = new LockedException('User account is locked.');
            $ex->setUser($user);
            throw $ex;
        }

        if (!$user->isEnabled()) {
            $ex = new DisabledException('User account is disabled.');
            $ex->setUser($user);
            throw $ex;
        }

        if (!$user->isAccountNonExpired()) {
            $ex = new AccountExpiredException('User account has expired.');
            $ex->setUser($user);
            throw $ex;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkPostAuth(UserInterface $user)
    {
        if (!$user instanceof AdvancedUserInterface && !$user instanceof User) {
            return;
        }

        if ($user instanceof AdvancedUserInterface && !$user instanceof User) {
            @trigger_error(sprintf('Calling %s with an AdvancedUserInterface is deprecated since Symphony 4.1. Create a custom user checker if you wish to keep this functionality.', __METHOD__), E_USER_DEPRECATED);
        }

        if (!$user->isCredentialsNonExpired()) {
            $ex = new CredentialsExpiredException('User credentials have expired.');
            $ex->setUser($user);
            throw $ex;
        }
    }
}
