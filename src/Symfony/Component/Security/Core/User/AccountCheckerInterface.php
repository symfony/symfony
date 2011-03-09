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
 * AccountCheckerInterface checks user account when authentication occurs.
 *
 * This should not be used to make authentication decisions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface AccountCheckerInterface
{
    /**
     * Checks the user account before authentication.
     *
     * @param AccountInterface $account An AccountInterface instance
     */
    function checkPreAuth(AccountInterface $account);

    /**
     * Checks the user account after authentication.
     *
     * @param AccountInterface $account An AccountInterface instance
     */
    function checkPostAuth(AccountInterface $account);
}
