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
    private $hierarchy;
    protected $map;

    /**
     * @param array $hierarchy An array defining the hierarchy
     */
    public function __construct(array $hierarchy)
    {
        $this->hierarchy = $hierarchy;

        $this->buildRoleMap();
    }

    /**
     * {@inheritdoc}
     */
    public function getReachableRoles(array $roles)
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            @trigger_error(sprintf('The %s() method is deprecated since Symfony 4.3 and will be removed in 5.0. Use roles as strings and the getReachableRoleNames() method instead.', __METHOD__), E_USER_DEPRECATED);
        }

        $reachableRoles = $roles;
        foreach ($roles as $role) {
            if (!isset($this->map[$role->getRole()])) {
                continue;
            }

            foreach ($this->map[$role->getRole()] as $r) {
                $reachableRoles[] = new Role($r);
            }
        }

        return $reachableRoles;
    }

    /**
     * @param string[] $roles
     *
     * @return string[]
     */
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

        return $reachableRoles;
    }

    protected function buildRoleMap()
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
