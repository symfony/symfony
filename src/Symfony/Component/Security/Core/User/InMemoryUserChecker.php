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

use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\LockedException;

/**
 * Checks the state of the in-memory user account.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InMemoryUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user)
    {
        // @deprecated since Symfony 5.3, in 6.0 change to:
        // if (!$user instanceof InMemoryUser) {
        if (!$user instanceof InMemoryUser && !$user instanceof User) {
            return;
        }

        if (!$user->isEnabled()) {
            $ex = new DisabledException('User account is disabled.');
            $ex->setUser($user);
            throw $ex;
        }

        // @deprecated since Symfony 5.3
        if (User::class === \get_class($user)) {
            if (!$user->isAccountNonLocked()) {
                $ex = new LockedException('User account is locked.');
                $ex->setUser($user);
                throw $ex;
            }

            if (!$user->isAccountNonExpired()) {
                $ex = new AccountExpiredException('User account has expired.');
                $ex->setUser($user);
                throw $ex;
            }
        }
    }

    public function checkPostAuth(UserInterface $user)
    {
        // @deprecated since Symfony 5.3, noop in 6.0
        if (User::class !== \get_class($user)) {
            return;
        }

        if (!$user->isCredentialsNonExpired()) {
            $ex = new CredentialsExpiredException('User credentials have expired.');
            $ex->setUser($user);
            throw $ex;
        }
    }
}

if (!class_exists(UserChecker::class, false)) {
    class_alias(InMemoryUserChecker::class, UserChecker::class);
}
