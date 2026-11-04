<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\GuardedBundle;

use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AppUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user)
    {
    }

    public function checkPostAuth(UserInterface $user)
    {
        if ('Expired' === $user->getUserIdentifier()) {
            throw new AccountExpiredException();
        }
    }
}
