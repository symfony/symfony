<?php

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\Role\RoleInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * RoleChecker gives a role comparison.
 *
 * @author Jérémy DECOOL <contact@jdecool.fr>
 */
class RoleChecker implements RoleCheckerInterface
{
    private $authorizationChecker;
    private $roleHierarchy;

    /**
     * Constructor.
     *
     * @param RoleHierarchyInterface $roleHierarchy
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, RoleHierarchyInterface $roleHierarchy)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->roleHierarchy = $roleHierarchy;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     */
    public function hasRole($role, UserInterface $user = null)
    {
        if (null === $user) {
            return $this->authorizationChecker->isGranted($role);
        }

        $roles = $this->roleHierarchy->getReachableRoles(array_map(function ($role) {
            if (is_string($role)) {
                return new Role($role);
            } elseif (!$role instanceof RoleInterface) {
                throw new \InvalidArgumentException(sprintf('$roles must be an array of strings, or RoleInterface instances, but got %s.', gettype($role)));
            }

            return $role;
        }, $user->getRoles()));

        return in_array(new Role($role), $roles);
    }
}
