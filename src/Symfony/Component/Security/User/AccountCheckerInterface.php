<?php

namespace Symfony\Component\Security\User;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * AccountCheckerInterface checks user account when authentication occurs.
 *
 * This should not be used to make authentication decisions.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
