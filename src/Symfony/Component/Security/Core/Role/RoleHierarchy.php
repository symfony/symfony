<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Role;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoleHierarchy implements RoleHierarchyInterface
{
    /** @var array<string, array<string, string>> */
    protected array $map;

    /**
     * @param array<string, list<string>> $hierarchy
     */
    public function __construct(
        private array $hierarchy,
    ) {
        $this->buildRoleMap();
    }

    public function getReachableRoleNames(array $roles): array
    {
        $reachableRoles = [];

        foreach ($roles as $role) {
            $reachableRoles[$role] = $role;

            if (!isset($this->map[$role])) {
                continue;
            }

            foreach ($this->map[$role] as $r) {
                $reachableRoles[$r] = $r;
            }
        }

        return $reachableRoles;
    }

    /**
     * @param string[] $roles
     *
     * @return array<string, string>
     */
    public function getParentRoleNames(array $roles): array
    {
        $parentRoles = [];

        foreach ($roles as $role) {
            $parentRoles[$role] = $role;

            foreach ($this->map as $parent => $children) {
                if (isset($children[$role])) {
                    $parentRoles[$parent] = $parent;
                }
            }
        }

        return $parentRoles;
    }

    protected function buildRoleMap(): void
    {
        $this->map = [];
        foreach ($this->hierarchy as $main => $roles) {
            $map = [];
            $visited = [];
            $additionalRoles = $roles;
            while (null !== $role = key($additionalRoles)) {
                $role = $additionalRoles[$role];
                $map[$role] = $role;

                if (!isset($this->hierarchy[$role])) {
                    next($additionalRoles);
                    continue;
                }

                $visited[] = $role;

                foreach ($this->hierarchy[$role] as $roleToAdd) {
                    $map[$roleToAdd] = $roleToAdd;
                }

                foreach (array_diff($this->hierarchy[$role], $visited) as $additionalRole) {
                    $additionalRoles[] = $additionalRole;
                }

                next($additionalRoles);
            }

            $this->map[$main] = $map;
        }
    }
}
