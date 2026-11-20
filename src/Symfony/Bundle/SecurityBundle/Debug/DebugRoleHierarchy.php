<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Debug;

use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * Extended Role Hierarchy to access inner configuration data.
 *
 * @author Nicolas Rigaud <squrious@protonmail.com>
 *
 * @internal
 */
final class DebugRoleHierarchy extends RoleHierarchy
{
    private readonly array $debugHierarchy;

    public function __construct(array $hierarchy)
    {
        $this->debugHierarchy = $hierarchy;

        parent::__construct($hierarchy);
    }

    /**
     * Get the hierarchy tree.
     *
     * Example output:
     *
     *     [
     *       'ROLE_A' => [
     *          'ROLE_B' => [],
     *          'ROLE_C' => [
     *             'ROLE_D' => []
     *          ]
     *       ],
     *       'ROLE_C' => [
     *          'ROLE_D' => []
     *       ]
     *     ]
     *
     * @param string[] $roles Optionally restrict the tree to these roles
     *
     * @return array<string,array<string,array>>
     */
    public function getHierarchy(array $roles = []): array
    {
        return $this->buildHierarchy($roles ?: array_keys($this->debugHierarchy));
    }

    /**
     * Get the computed role map.
     *
     * @return array<string,string[]>
     */
    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * Return whether a given role is processed as a placeholder.
     */
    public function isPlaceholder(string $role): bool
    {
        return \in_array($role, array_keys($this->rolePlaceholdersPatterns));
    }

    private function buildHierarchy(array $roles, array $ancestors = []): array
    {
        $tree = [];
        foreach ($roles as $role) {
            if (isset($ancestors[$role]) || isset($tree[$role])) {
                continue;
            }

            $inherited = array_merge($this->getMatchingPlaceholders([$role]), $this->debugHierarchy[$role] ?? []);
            $tree[$role] = $this->buildHierarchy($inherited, $ancestors + [$role => true]);
        }

        return $tree;
    }
}
