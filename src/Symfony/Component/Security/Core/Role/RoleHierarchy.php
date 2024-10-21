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
 * RoleHierarchy defines a role hierarchy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoleHierarchy implements RoleHierarchyInterface
{
    /** @var array<string, list<string>> */
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
        $reachableRoles = $roles;

        foreach ($roles as $role) {
            if (!isset($this->map[$role])) {
                continue;
            }

            foreach ($this->map[$role] as $r) {
                $reachableRoles[] = $r;
            }
        }

        return array_values(array_unique($reachableRoles));
    }

    public function getEncompassingRoleNames(array $roles): array
    {
        $encompassingRoles = $roles;

        foreach ($roles as $role) {
            foreach ($this->map as $parent => $children) {
                if (\in_array($role, $children, true)) {
                    $encompassingRoles[] = $parent;
                }
            }
        }

        return array_values(array_unique($encompassingRoles));
    }

    protected function buildRoleMap(): void
    {
        $this->map = [];
        foreach ($this->hierarchy as $main => $roles) {
            $this->map[$main] = $roles;
            $visited = [];
            $additionalRoles = $roles;
            while ($role = array_shift($additionalRoles)) {
                if (!isset($this->hierarchy[$role])) {
                    continue;
                }

                $visited[] = $role;

                foreach ($this->hierarchy[$role] as $roleToAdd) {
                    $this->map[$main][] = $roleToAdd;
                }

                foreach (array_diff($this->hierarchy[$role], $visited) as $additionalRole) {
                    $additionalRoles[] = $additionalRole;
                }
            }

            $this->map[$main] = array_unique($this->map[$main]);
        }
    }
}
