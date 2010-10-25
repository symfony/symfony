<?php

namespace Symfony\Component\Security\User;

use Symfony\Component\Security\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Exception\LockedException;
use Symfony\Component\Security\Exception\DisabledException;
use Symfony\Component\Security\Exception\AccountExpiredException;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * AccountChecker checks the user account flags.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
