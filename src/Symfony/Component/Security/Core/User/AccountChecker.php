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

use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;

/**
 * AccountChecker checks the user account flags.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AccountChecker implements AccountCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function checkPreAuth(AccountInterface $account)
    {
        if (!$account instanceof AdvancedAccountInterface) {
            return;
        }

        if (!$account->isCredentialsNonExpired()) {
            throw new CredentialsExpiredException('User credentials have expired.', $account);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkPostAuth(AccountInterface $account)
    {
        if (!$account instanceof AdvancedAccountInterface) {
            return;
        }

        if (!$account->isAccountNonLocked()) {
            throw new LockedException('User account is locked.', $account);
        }

        if (!$account->isEnabled()) {
            throw new DisabledException('User account is disabled.', $account);
        }

        if (!$account->isAccountNonExpired()) {
            throw new AccountExpiredException('User account has expired.', $account);
        }
    }
}
