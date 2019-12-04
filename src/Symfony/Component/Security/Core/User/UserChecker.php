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

use Symfony\Component\Security\Core\User\UserChecker\AccountNonExpired;
use Symfony\Component\Security\Core\User\UserChecker\AccountNonLockedChecker;
use Symfony\Component\Security\Core\User\UserChecker\CredentialsNonExpired;
use Symfony\Component\Security\Core\User\UserChecker\EnabledChecker;

/**
 * UserChecker checks the user account flags.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UserChecker implements UserCheckerInterface
{
    private $chain;

    public function __construct()
    {
        @trigger_error('Usage of class is deprecated. Use the ChainUserChecker instead.', E_USER_DEPRECATED);

        $this->chain = new ChainUserChecker();
        $this->chain->add(new AccountNonLockedChecker());
        $this->chain->add(new EnabledChecker());
        $this->chain->add(new AccountNonExpired());
        $this->chain->add(new CredentialsNonExpired());
    }

    /**
     * {@inheritdoc}
     */
    public function checkPreAuth(UserInterface $user)
    {
        $this->chain->checkPreAuth($user);
    }

    /**
     * {@inheritdoc}
     */
    public function checkPostAuth(UserInterface $user)
    {
        $this->chain->checkPostAuth($user);
    }
}
