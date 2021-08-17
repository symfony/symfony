<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\RememberMeBundle\Security;

use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserChangingUserProvider implements UserProviderInterface
{
    private $inner;

    public function __construct(InMemoryUserProvider $inner)
    {
        $this->inner = $inner;
    }

    public function loadUserByUsername($username)
    {
        return $this->inner->loadUserByUsername($username);
    }

    public function loadUserByIdentifier(string $userIdentifier): UserInterface
    {
        return $this->inner->loadUserByIdentifier($userIdentifier);
    }

    public function refreshUser(UserInterface $user)
    {
        $user = $this->inner->refreshUser($user);

        $alterUser = \Closure::bind(function (InMemoryUser $user) { $user->password = 'foo'; }, null, class_exists(User::class) ? User::class : InMemoryUser::class);
        $alterUser($user);

        return $user;
    }

    public function supportsClass($class)
    {
        return $this->inner->supportsClass($class);
    }
}
