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
        $reachableRoles = array_combine($roles, $roles);

        foreach ($roles as $role) {
            if (!isset($this->map[$role])) {
                continue;
            }

            foreach ($this->map[$role] as $r) {
                $reachableRoles[$r] = $r;
            }
        }

        return array_keys($reachableRoles);
    }

    protected function buildRoleMap(): void
    {
        $this->map = [];
        foreach ($this->hierarchy as $main => $roles) {
            $this->map[$main] = $roles;
            $visited = [];
            $additionalRoles = $roles;
            while (null !== $role = key($additionalRoles)) {
                $role = $additionalRoles[$role];

                if (!isset($this->hierarchy[$role])) {
                    next($additionalRoles);
                    continue;
                }

                $visited[] = $role;

                foreach ($this->hierarchy[$role] as $roleToAdd) {
                    $this->map[$main][] = $roleToAdd;
                }

                foreach (array_diff($this->hierarchy[$role], $visited) as $additionalRole) {
                    $additionalRoles[] = $additionalRole;
                }

                next($additionalRoles);
            }

            $this->map[$main] = array_unique($this->map[$main]);
        }
    }
}
