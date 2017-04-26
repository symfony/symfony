<?php

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\User\UserInterface;

interface RoleCheckerInterface
{
    /**
     * @param string             $role Role to check
     * @param UserInterface|null $user A user instance
     *
     * @return bool
     */
    public function hasRole($role, UserInterface $user = null);
}
