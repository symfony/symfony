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
        $hierarchy = [];

        foreach ($roles ?: array_keys($this->debugHierarchy) as $role) {
            $hierarchy += $this->buildHierarchy([$role]);
        }

        return $hierarchy;
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

    private function buildHierarchy(array $roles, array &$visited = []): array
    {
        $tree = [];
        foreach ($roles as $role) {
            $visited[] = $role;

            $tree[$role] = [];

            // Get placeholders matches
            $placeholders = array_diff($this->getMatchingPlaceholders([$role]), $visited) ?? [];
            array_push($visited, ...$placeholders);
            $tree[$role] += $this->buildHierarchy($placeholders, $visited);

            // Get regular inherited roles
            $inherited = array_diff($this->debugHierarchy[$role] ?? [], $visited);
            array_push($visited, ...$inherited);
            $tree[$role] += $this->buildHierarchy($inherited, $visited);
        }

        return $tree;
    }
}
