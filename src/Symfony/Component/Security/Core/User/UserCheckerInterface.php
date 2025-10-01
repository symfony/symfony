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
     * @param ?TokenInterface $token
     *
     * @throws AccountStatusException
     */
    public function checkPostAuth(UserInterface $user /* , ?TokenInterface $token = null */): void;
}
